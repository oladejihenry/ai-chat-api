# T3 Chat Clone API Setup Guide

## Overview

This is a Laravel-based API for an AI chat platform that supports multiple AI providers (OpenAI, Anthropic) with conversation management and real-time message streaming.

## Architecture

### Controller Responsibilities

-   **MessageController**: Handles AI interactions, streaming responses to Next.js frontend, and message CRUD operations
-   **ConversationController**: Manages conversations, stores AI responses, and handles conversation lifecycle
-   **ChatController**: Provides utility functions like health checks and available models

### Key Features

-   🤖 Multi-AI Provider Support (OpenAI, Anthropic)
-   💬 Conversation Management
-   📝 Message History with Streaming
-   🔐 User Authentication with Sanctum
-   🎯 RESTful API Design
-   📊 Pagination Support
-   🧪 Comprehensive Testing Setup
-   🌊 Real-time Streaming to Next.js

## Database Schema

### Conversations Table

-   `id` - Primary key
-   `user_id` - Foreign key to users table
-   `title` - Conversation title
-   `model_name` - AI model used (e.g., gpt-4, claude-3-sonnet)
-   `model_provider` - AI provider (openai, anthropic)
-   `created_at` / `updated_at` - Timestamps

### Messages Table

-   `id` - Primary key
-   `conversation_id` - Foreign key to conversations table
-   `content` - Message content
-   `role` - Message role (user, assistant)
-   `model_name` - AI model used for assistant messages
-   `created_at` / `updated_at` - Timestamps

## Installation

### 1. Environment Setup

Create a `.env` file with the following variables:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=t3chat_api
DB_USERNAME=your_username
DB_PASSWORD=your_password

# AI Services
OPENAI_API_KEY=your_openai_api_key
ANTHROPIC_API_KEY=your_anthropic_api_key

# Google OAuth (if using)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=your_redirect_uri
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

## API Endpoints

### Authentication

All endpoints require authentication using Laravel Sanctum.

### Message Endpoints (AI Interactions & Streaming)

#### Send Message to Conversation (Supports Streaming)

```http
POST /api/conversations/{conversation_id}/messages
Content-Type: application/json
Authorization: Bearer {token}
Accept: text/event-stream  # For streaming response

{
    "content": "Tell me a joke",
    "stream": true,
    "options": {
        "temperature": 0.9,
        "max_tokens": 500
    }
}
```

**Streaming Response Format:**

```
event: start
data: {"status": "generating"}

event: chunk
data: {"content": "Here's "}

event: chunk
data: {"content": "a joke..."}

event: complete
data: {"message": {...}, "status": "completed"}
```

#### Get Messages for Conversation

```http
GET /api/messages?conversation_id=1
Authorization: Bearer {token}
```

### Conversation Management

#### Start New Conversation with First Message

```http
POST /api/conversations/start
Content-Type: application/json
Authorization: Bearer {token}

{
    "title": "My AI Conversation",
    "model_name": "gpt-4",
    "model_provider": "openai",
    "content": "Hello, how are you?",
    "options": {
        "temperature": 0.7,
        "max_tokens": 1000
    }
}
```

#### List User Conversations

```http
GET /api/conversations
Authorization: Bearer {token}
```

#### Get Specific Conversation

```http
GET /api/conversations/{id}
Authorization: Bearer {token}
```

#### Update Conversation

```http
PUT /api/conversations/{id}
Authorization: Bearer {token}

{
    "title": "Updated Title",
    "model_name": "claude-3-sonnet",
    "model_provider": "anthropic"
}
```

#### Delete Conversation

```http
DELETE /api/conversations/{id}
Authorization: Bearer {token}
```

### Chat Utility Endpoints

#### Get Available Models

```http
GET /api/chat/models
Authorization: Bearer {token}
```

#### Health Check

```http
GET /api/chat/health
Authorization: Bearer {token}
```

## Streaming Integration with Next.js

### Frontend Implementation Example

```javascript
// Using EventSource for Server-Sent Events
const sendMessage = async (conversationId, content) => {
    const eventSource = new EventSource(
        `/api/conversations/${conversationId}/messages`,
        {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: "text/event-stream",
            },
        }
    );

    eventSource.addEventListener("start", (event) => {
        const data = JSON.parse(event.data);
        console.log("AI started generating:", data.status);
    });

    eventSource.addEventListener("chunk", (event) => {
        const data = JSON.parse(event.data);
        // Update UI with streaming content
        updateMessageContent(data.content);
    });

    eventSource.addEventListener("complete", (event) => {
        const data = JSON.parse(event.data);
        // Message complete, store final message
        finalizeMessage(data.message);
        eventSource.close();
    });

    eventSource.addEventListener("error", (event) => {
        const data = JSON.parse(event.data);
        console.error("Streaming error:", data.error);
        eventSource.close();
    });
};
```

### Using Fetch API (Alternative)

```javascript
const response = await fetch(`/api/conversations/${conversationId}/messages`, {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        Accept: "text/event-stream",
    },
    body: JSON.stringify({
        content: message,
        stream: true,
    }),
});

const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    const chunk = decoder.decode(value);
    // Process Server-Sent Events format
    processSSEChunk(chunk);
}
```

## Response Format

### Success Response

```json
{
    "message": "Success message",
    "data": {
        // Response data
    }
}
```

### Error Response

```json
{
    "message": "Error message",
    "error": "Detailed error information"
}
```

### Paginated Response

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

## AI Provider Configuration

### OpenAI

-   Supports: GPT-4, GPT-4 Turbo, GPT-3.5 Turbo
-   API Key required in `OPENAI_API_KEY` environment variable

### Anthropic

-   Supports: Claude-3 Opus, Claude-3 Sonnet, Claude-3 Haiku
-   API Key required in `ANTHROPIC_API_KEY` environment variable

## Testing

### Run Tests

```bash
php artisan test
```

### Run Linting

```bash
composer lint
```

## Security Features

-   Authentication required for all endpoints
-   User ownership validation for conversations and messages
-   Input validation and sanitization
-   Rate limiting (configure in RouteServiceProvider)
-   CORS support for Next.js frontend

## Development

### Start Development Server

```bash
php artisan serve
```

### Queue Workers (for background jobs)

```bash
php artisan queue:work
```

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Set up proper web server configuration
6. Configure queue workers with supervisor
7. Set up proper logging and monitoring

## Troubleshooting

### Common Issues

1. **Migration Errors**: Ensure database connection is properly configured
2. **AI API Errors**: Verify API keys are correct and have sufficient credits
3. **Authentication Issues**: Ensure Sanctum is properly configured
4. **CORS Issues**: Configure CORS settings in `config/cors.php`
5. **Streaming Issues**: Check that your web server supports Server-Sent Events

### Logs

Check Laravel logs in `storage/logs/laravel.log` for detailed error information.

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation
4. Run `composer lint` before committing

## License

This project is open-sourced software licensed under the MIT license.
