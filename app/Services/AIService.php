<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected array $providers = [
        'openai' => [
            'base_url' => 'https://api.openai.com/v1',
            'models' => [
                'gpt-4o' => 'gpt-4o',
                'gpt-4o-mini' => 'gpt-4o-mini',
                'gpt-4-turbo' => 'gpt-4-turbo',
                'gpt-4' => 'gpt-4',
                'gpt-3.5-turbo' => 'gpt-3.5-turbo'
            ],
        ],
        'anthropic' => [
            'base_url' => 'https://api.anthropic.com/v1',
            'models' => [
                // Claude 4 models (latest)
                'claude-opus-4' => 'claude-opus-4-20250514',
                'claude-sonnet-4' => 'claude-sonnet-4-20250514',

                // Claude 3.x models
                'claude-3-7-sonnet' => 'claude-3-7-sonnet-20250219',
                'claude-3-5-sonnet' => 'claude-3-5-sonnet-20241022',
                'claude-3-5-haiku' => 'claude-3-5-haiku-20241022',
                'claude-3-opus' => 'claude-3-opus-20240229',
                'claude-3-sonnet' => 'claude-3-sonnet-20240229',
                'claude-3-haiku' => 'claude-3-haiku-20240307',
            ],
        ],

        'deepseek' => [
            'base_url' => 'https://api.deepseek.com/v1',
            'models' => [
                'deepseek-chat' => 'deepseek-chat',
            ],
        ],

        'gemini' => [
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
            'models' => [
                'gemini-1.5-flash' => 'gemini-1.5-flash',
                'gemini-2.0-flash-exp' => 'gemini-2.0-flash-exp',
                'gemini-2.0-flash-lite-exp' => 'gemini-2.0-flash-lite-exp',
                'gemini-2.0-flash-lite-preview-02-05' => 'gemini-2.0-flash-lite-preview-02-05',
                'gemini-2.0-flash-lite-preview-02-05' => 'gemini-2.0-flash-lite-preview-02-05',
                'gemini-2.0-flash' => 'gemini-2.0-flash',
            ],
        ],

        'mistral' => [
            'base_url' => 'https://api.mistral.ai/v1',
            'models' => [
                'mistral-large-latest' => 'mistral-large-latest',
            ],
        ],
    ];

    /**
     * Generate AI response based on conversation messages (non-streaming)
     */
    public function generateResponse(
        string $provider,
        string $model,
        array $messages,
        array $options = []
    ): array {
        try {
            // Get the actual API model name
            $apiModel = $this->getApiModelName($provider, $model);

            Log::info('Generating response', [
                'provider' => $provider,
                'model' => $model,
                'api_model' => $apiModel,
                'message_count' => count($messages)
            ]);

            return match ($provider) {
                'openai' => $this->callOpenAI($apiModel, $messages, $options),
                'anthropic' => $this->callAnthropic($apiModel, $messages, $options),
                'deepseek' => $this->callDeepSeek($apiModel, $messages, $options),
                'gemini' => $this->callGemini($apiModel, $messages, $options),
                'mistral' => $this->callMistral($apiModel, $messages, $options),
                default => throw new \InvalidArgumentException("Unsupported provider: {$provider}")
            };
        } catch (\Exception $e) {
            Log::error('AI Service Error', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate streaming AI response
     */
    public function generateStreamingResponse(
        string $provider,
        string $model,
        array $messages,
        array $options = []
    ): \Generator {
        try {
            // Get the actual API model name
            $apiModel = $this->getApiModelName($provider, $model);

            Log::info('Generating streaming response', [
                'provider' => $provider,
                'model' => $model,
                'api_model' => $apiModel,
                'message_count' => count($messages)
            ]);

            return match ($provider) {
                'openai' => $this->streamOpenAI($apiModel, $messages, $options),
                'anthropic' => $this->streamAnthropic($apiModel, $messages, $options),
                'deepseek' => $this->streamDeepSeek($apiModel, $messages, $options),
                'gemini' => $this->streamGemini($apiModel, $messages, $options),
                'mistral' => $this->streamMistral($apiModel, $messages, $options),
                default => throw new \InvalidArgumentException("Unsupported provider: {$provider}")
            };
        } catch (\Exception $e) {
            Log::error('AI Service Streaming Error', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the actual API model name for a provider
     */
    protected function getApiModelName(string $provider, string $model): string
    {
        $models = $this->providers[$provider]['models'] ?? [];

        // If it's already a full API model name, return it
        if (in_array($model, $models)) {
            return $model;
        }

        // Look for the model in the mapping
        return $models[$model] ?? $model;
    }

    /**
     * Call OpenAI API (non-streaming)
     */
    protected function callOpenAI(string $model, array $messages, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['openai']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => false, // Explicitly set to false for non-streaming
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API Error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'],
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * Stream OpenAI API response
     */
    protected function streamOpenAI(string $model, array $messages, array $options = []): \Generator
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['openai']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API Error: ' . $response->body());
        }

        // Parse the streaming response
        $body = $response->body();
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            if (empty($line) || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6); // Remove "data: " prefix

            if ($data === '[DONE]') {
                break;
            }

            $json = json_decode($data, true);
            if ($json && isset($json['choices'][0]['delta']['content'])) {
                yield $json['choices'][0]['delta']['content'];
            }
        }
    }

    /**
     * Call Anthropic API (non-streaming)
     */
    protected function callAnthropic(string $model, array $messages, array $options = []): array
    {
        // Convert OpenAI format to Anthropic format
        $anthropicMessages = $this->convertToAnthropicFormat($messages);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->post($this->providers['anthropic']['base_url'] . '/messages', [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Anthropic API Error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'],
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * Call DeepSeek API (non-streaming)
     */
    protected function callDeepSeek(string $model, array $messages, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['deepseek']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response->successful()) {
            throw new \Exception('DeepSeek API Error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'],
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * Stream DeepSeek API response
     */
    protected function streamDeepSeek(string $model, array $messages, array $options = []): \Generator
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['deepseek']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ]);

        if (!$response->successful()) {
            throw new \Exception('DeepSeek API Error: ' . $response->body());
        }

        $body = $response->body();
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            if (empty($line) || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6); // Remove "data: " prefix

            if ($data === '[DONE]') {
                break;
            }

            $json = json_decode($data, true);
            if ($json && isset($json['choices'][0]['delta']['content'])) {
                yield $json['choices'][0]['delta']['content'];
            }
        }
    }

    /**
     * Stream Anthropic API response (simulated for now)
     */
    protected function streamAnthropic(string $model, array $messages, array $options = []): \Generator
    {
        // Anthropic doesn't support streaming in the same way as OpenAI
        // For now, we'll get the full response and simulate streaming
        $response = $this->callAnthropic($model, $messages, $options);
        $content = $response['content'];

        // Simulate streaming by breaking content into words
        $words = explode(' ', $content);
        foreach ($words as $word) {
            yield $word . ' ';
            usleep(50000); // 50ms delay for realistic streaming effect
        }
    }

    /**
     * Call Gemini API (non-streaming)
     */
    protected function callGemini(string $model, array $messages, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.gemini.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['gemini']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gemini API Error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'],
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * Call Mistral API (non-streaming)
     */
    protected function callMistral(string $model, array $messages, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.mistral.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['mistral']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Mistral API Error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'],
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * Stream Gemini API response
     */
    protected function streamGemini(string $model, array $messages, array $options = []): \Generator
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.gemini.api_key'),
                'Content-Type' => 'application/json',
            ])->post($this->providers['gemini']['base_url'] . '?key=' . config('services.gemini.api_key'), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $messages[0]['content']]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                throw new \Exception('Gemini API Error: ' . $response->body());
            }

            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                if (empty($line) || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6); // Remove "data: " prefix

                if ($data === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    yield $json['choices'][0]['delta']['content'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'error' => $e->getMessage()
            ]);
            return 'Gemini API Error: ' . $e->getMessage();
        }
    }

    /**
     * Stream Mistral API response
     */
    protected function streamMistral(string $model, array $messages, array $options = []): \Generator
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.mistral.api_key'),
            'Content-Type' => 'application/json',
        ])->post($this->providers['mistral']['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Mistral API Error: ' . $response->body());
        }

        $body = $response->body();
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            if (empty($line) || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6); // Remove "data: " prefix

            if ($data === '[DONE]') {
                break;
            }

            $json = json_decode($data, true);
            if ($json && isset($json['choices'][0]['delta']['content'])) {
                yield $json['choices'][0]['delta']['content'];
            }
        }
    }

    /**
     * Convert OpenAI message format to Anthropic format
     */
    protected function convertToAnthropicFormat(array $messages): array
    {
        return array_map(function ($message) {
            return [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }, array_filter($messages, fn($msg) => $msg['role'] !== 'system'));
    }

    /**
     * Get available models for a provider (returns user-friendly names)
     */
    public function getAvailableModels(string $provider): array
    {
        $models = $this->providers[$provider]['models'] ?? [];

        if ($provider === 'anthropic') {
            // Return user-friendly names as keys
            return array_keys($models);
        }

        if ($provider === 'openai') {
            // Return user-friendly names as keys
            return array_keys($models);
        }

        return $models;
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get model information including API names
     */
    public function getModelInfo(string $provider): array
    {
        return $this->providers[$provider]['models'] ?? [];
    }
}
