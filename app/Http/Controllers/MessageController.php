<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Conversation;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function __construct(
        protected AIService $aiService
    ) {}

    /**
     * Display a listing of messages for a conversation.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);

        // Check if user owns the conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'data' => MessageResource::collection($messages->items()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ]);
    }

    /**
     * Store a newly created message and get AI response with streaming support.
     */
    public function store(Conversation $conversation, StoreMessageRequest $request): JsonResponse|StreamedResponse
    {
        Log::info('Message store request', $request->all());

        $user = $request->user();

        // Check if user owns the conversation
        if ($conversation->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Use model from request if provided, otherwise fall back to conversation defaults
        $modelName = $request->input('model_name') ? strtolower($request->input('model_name')) : $conversation->model_name;
        $modelProvider = $request->input('model_provider') ? strtolower($request->input('model_provider')) : $conversation->model_provider;

        Log::info('Using AI model', [
            'conversation_model' => $conversation->model_name,
            'conversation_provider' => $conversation->model_provider,
            'request_model' => $request->input('model_name'),
            'request_provider' => $request->input('model_provider'),
            'final_model' => $modelName,
            'final_provider' => $modelProvider,
        ]);

        try {
            DB::beginTransaction();

            // Store user message
            $userMessage = $conversation->messages()->create([
                'content' => $request->content,
                'role' => 'user',
                'model_name' => null,
            ]);

            // Get conversation history for context
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                })
                ->toArray();

            DB::commit();

            // Check if streaming is requested
            if ($request->header('Accept') === 'text/event-stream' || $request->boolean('stream')) {
                return $this->streamAIResponse($conversation, $messages, $request, $modelProvider, $modelName);
            }

            // Generate AI response (non-streaming) using the selected model
            $aiResponse = $this->aiService->generateResponse(
                $modelProvider,
                $modelName,
                $messages,
                $request->input('options', [])
            );

            // Store AI response with the actual model used
            $assistantMessage = $conversation->messages()->create([
                'content' => $aiResponse['content'],
                'role' => 'assistant',
                'model_name' => $aiResponse['model'],
            ]);

            return response()->json([
                'message' => 'Message sent successfully',
                'data' => [
                    'user_message' => new MessageResource($userMessage),
                    'assistant_message' => new MessageResource($assistantMessage),
                    'usage' => $aiResponse['usage'] ?? null,
                    'model_used' => [
                        'provider' => $modelProvider,
                        'model' => $modelName,
                        'api_model' => $aiResponse['model'],
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process message', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'model_provider' => $modelProvider,
                'model_name' => $modelName,
            ]);

            return response()->json([
                'message' => 'Failed to generate AI response',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stream AI response to Next.js frontend
     */
    protected function streamAIResponse(
        Conversation $conversation,
        array $messages,
        Request $request,
        string $modelProvider,
        string $modelName
    ): StreamedResponse {
        return new StreamedResponse(function () use ($conversation, $messages, $request, $modelProvider, $modelName) {
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering

            try {
                // Start streaming
                echo "event: start\n";
                echo "data: " . json_encode([
                    'status' => 'generating',
                    'model' => $modelName,
                    'provider' => $modelProvider
                ]) . "\n\n";
                ob_flush();
                flush();

                $fullContent = '';
                $chunks = $this->generateStreamingResponse(
                    $modelProvider,
                    $modelName,
                    $messages,
                    $request->input('options', [])
                );

                foreach ($chunks as $chunk) {
                    if (!empty($chunk)) {
                        $fullContent .= $chunk;
                        echo "event: chunk\n";
                        echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

                // Store the complete AI response with the actual model used
                $assistantMessage = $conversation->messages()->create([
                    'content' => $fullContent,
                    'role' => 'assistant',
                    'model_name' => $modelName, // Store the model that was actually used
                ]);

                // Send completion event
                echo "event: complete\n";
                echo "data: " . json_encode([
                    'message' => new MessageResource($assistantMessage),
                    'status' => 'completed',
                    'model_used' => [
                        'provider' => $modelProvider,
                        'model' => $modelName,
                    ]
                ]) . "\n\n";
            } catch (\Exception $e) {
                Log::error('Streaming error', [
                    'error' => $e->getMessage(),
                    'model_provider' => $modelProvider,
                    'model_name' => $modelName,
                ]);
                echo "event: error\n";
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            }

            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    /**
     * Generate streaming response from AI service
     */
    protected function generateStreamingResponse(string $provider, string $model, array $messages, array $options = []): \Generator
    {
        // Use the new streaming method from AIService
        try {
            return $this->aiService->generateStreamingResponse($provider, $model, $messages, $options);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Display the specified message.
     */
    public function show(Request $request, Message $message): JsonResponse
    {
        // Check if user owns the conversation
        if ($message->conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => new MessageResource($message)
        ]);
    }

    /**
     * Update the specified message.
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        // Check if user owns the conversation
        if ($message->conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'content' => 'sometimes|string',
            'model_name' => 'sometimes|nullable|string|max:100',
        ]);

        $message->update($validatedData);

        return response()->json([
            'message' => 'Message updated successfully',
            'data' => new MessageResource($message)
        ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        // Check if user owns the conversation
        if ($message->conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }
}
