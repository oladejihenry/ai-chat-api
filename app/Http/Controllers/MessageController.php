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

            // Process uploaded files if any
            $imageData = [];
            if ($request->hasFile('files')) {
                $imageData = $this->processUploadedImages($request->file('files'));
            }

            // Store user message with image data
            $userMessage = $conversation->messages()->create([
                'content' => $request->content,
                'role' => 'user',
                'model_name' => null,
                'image_data' => !empty($imageData) ? json_encode($imageData) : null,
            ]);

            // Get conversation history for context
            $messages = $this->buildMessagesForAI($conversation, $request->content, $imageData);

            DB::commit();

            // Check if streaming is requested
            // if ($request->header('Accept') === 'text/event-stream' || $request->boolean('stream')) {
            return $this->streamAIResponse($conversation, $messages, $request, $modelProvider, $modelName);
            // }

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
     * Process uploaded images and convert them to base64
     */
    protected function processUploadedImages(array $files): array
    {
        $imageData = [];

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $mimeType = $file->getMimeType();
                $imageContent = base64_encode(file_get_contents($file->path()));

                $imageData[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mimeType};base64,{$imageContent}"
                    ]
                ];

                Log::info('Processed image', [
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        return $imageData;
    }

    /**
     * Build messages array for AI with support for images
     */
    protected function buildMessagesForAI(Conversation $conversation, string $currentText, array $imageData = []): array
    {
        // Get conversation history
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                // Handle text-only messages
                if (empty($message->image_data)) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                }

                // Handle messages with images
                $content = [
                    [
                        'type' => 'text',
                        'text' => $message->content,
                    ]
                ];

                // Add images from stored data
                $imageData = json_decode($message->image_data, true);
                if ($imageData) {
                    $content = array_merge($content, $imageData);
                }

                return [
                    'role' => $message->role,
                    'content' => $content,
                ];
            })
            ->toArray();

        // Handle the current message with images
        if (!empty($imageData)) {
            // Replace the last message (current user message) with proper format
            $lastMessageIndex = count($messages) - 1;

            $content = [
                [
                    'type' => 'text',
                    'text' => $currentText,
                ]
            ];

            $content = array_merge($content, $imageData);

            $messages[$lastMessageIndex] = [
                'role' => 'user',
                'content' => $content,
            ];
        }

        return $messages;
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


            $safeFlush = function () {
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            };

            try {
                // CREATE AND STORE USER MESSAGE FIRST
                $userMessage = $conversation->messages()->create([
                    'content' => $request->input('content'),
                    'role' => 'user',
                ]);

                // Start streaming
                echo "event: start\n";
                echo "data: " . json_encode([
                    'status' => 'generating',
                    'model' => $modelName,
                    'provider' => $modelProvider,
                    'has_images' => $this->messagesHaveImages($messages),
                ]) . "\n\n";
                $safeFlush();

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
                        $safeFlush();
                    }
                }

                // Store the complete AI response
                $assistantMessage = $conversation->messages()->create([
                    'content' => $fullContent,
                    'role' => 'assistant',
                    'model_name' => $modelName,
                ]);

                // Send completion event WITH BOTH MESSAGES
                echo "event: complete\n";
                echo "data: " . json_encode([
                    'message' => new MessageResource($assistantMessage),
                    'user_message' => new MessageResource($userMessage), // ADD THIS!
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

            $safeFlush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    /**
     * Check if messages contain images
     */
    protected function messagesHaveImages(array $messages): bool
    {
        foreach ($messages as $message) {
            if (isset($message['content']) && is_array($message['content'])) {
                foreach ($message['content'] as $content) {
                    if (isset($content['type']) && $content['type'] === 'image_url') {
                        return true;
                    }
                }
            }
        }
        return false;
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
