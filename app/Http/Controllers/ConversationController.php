<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function __construct(
        protected AIService $aiService
    ) {}

    /**
     * Display a listing of the user's conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->withCount('messages')
            ->with(['messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => ConversationResource::collection($conversations->items()),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ]
        ]);
    }

    /**
     * Store a newly created conversation.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = $request->user()->conversations()->create([
            'title' => $request->title,
            'model_name' => strtolower($request->model_name),
            'model_provider' => strtolower($request->model_provider),
        ]);

        return response()->json([
            'message' => 'Conversation created successfully',
            'data' => new ConversationResource($conversation)
        ], 201);
    }

    /**
     * Start a new conversation with first message and AI response
     */
    public function startWithMessage(StoreConversationRequest $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'options' => 'sometimes|array',
        ]);

        try {
            DB::beginTransaction();

            // Create conversation
            $conversation = $request->user()->conversations()->create([
                'title' => $request->title,
                'model_name' => strtolower($request->model_name),
                'model_provider' => strtolower($request->model_provider),
            ]);

            // Store user message
            $userMessage = $conversation->messages()->create([
                'content' => $request->content,
                'role' => 'user',
            ]);

            // Generate AI response
            $aiResponse = $this->aiService->generateResponse(
                $conversation->model_provider,
                $conversation->model_name,
                [['role' => 'user', 'content' => $request->content]],
                $request->input('options', [])
            );

            // Store AI response from selected model
            $assistantMessage = $conversation->messages()->create([
                'content' => $aiResponse['content'],
                'role' => 'assistant',
                'model_name' => $aiResponse['model'], // Store the actual model that responded
            ]);

            DB::commit();

            $conversation->load(['messages']);

            return response()->json([
                'message' => 'Conversation started successfully',
                'data' => [
                    'conversation' => new ConversationResource($conversation),
                    'usage' => $aiResponse['usage'] ?? null,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to start conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified conversation.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user owns the conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->load(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'data' => new ConversationResource($conversation)
        ]);
    }

    /**
     * Update the specified conversation.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user owns the conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|string|max:255',
            'model_name' => 'sometimes|string|max:100',
            'model_provider' => 'sometimes|string|max:50',
        ]);

        $conversation->update($validatedData);

        return response()->json([
            'message' => 'Conversation updated successfully',
            'data' => new ConversationResource($conversation)
        ]);
    }

    /**
     * Remove the specified conversation.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user owns the conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully'
        ]);
    }

    /**
     * Get available models for conversation creation
     */
    public function availableModels(): JsonResponse
    {
        $providers = $this->aiService->getAvailableProviders();
        $models = [];

        foreach ($providers as $provider) {
            $models[$provider] = $this->aiService->getAvailableModels($provider);
        }

        return response()->json([
            'data' => [
                'providers' => $providers,
                'models' => $models,
            ]
        ]);
    }
}
