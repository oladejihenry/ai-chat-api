<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required|string',
            'model_name' => 'sometimes|string|max:100',
            'model_provider' => 'sometimes|string|max:50|in:openai,anthropic,deepseek,gemini,mistral',
            'options' => 'sometimes|array',
            'options.temperature' => 'sometimes|numeric|between:0,2',
            'options.max_tokens' => 'sometimes|integer|min:1|max:4000',
            'stream' => 'sometimes|boolean',
            // 'role' => 'required|in:user,assistant',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conversation_id.required' => 'The conversation ID is required.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
            'content.required' => 'The message content is required.',
            'model_name.string' => 'The model name must be a string.',
            'model_provider.in' => 'The model provider must be either openai or anthropic.',
            'role.required' => 'The message role is required.',
            'role.in' => 'The role must be either user or assistant.',
        ];
    }
}
