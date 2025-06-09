<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected AIService $aiService
    ) {}

    /**
     * Get available AI models and providers
     */
    public function getAvailableModels(): JsonResponse
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

    /**
     * Health check for AI services
     */
    public function healthCheck(): JsonResponse
    {
        $status = [];
        $providers = $this->aiService->getAvailableProviders();

        foreach ($providers as $provider) {
            $status[$provider] = [
                'available' => true,
                'models' => $this->aiService->getAvailableModels($provider),
                'status' => 'operational'
            ];
        }

        return response()->json([
            'data' => $status,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
