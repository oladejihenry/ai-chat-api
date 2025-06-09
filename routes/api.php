<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ChatController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn(Request $request) => new UserResource($request->user()));

    // Chat utility endpoints
    Route::get('/chat/models', [ChatController::class, 'getAvailableModels']);
    Route::get('/chat/health', [ChatController::class, 'healthCheck']);

    // Conversations - handles conversation management and storing AI responses
    Route::resource('/conversations', ConversationController::class);
    Route::post('/conversations/start', [ConversationController::class, 'startWithMessage']);
    Route::get('/conversations/models', [ConversationController::class, 'availableModels']);

    // Messages - handles AI interactions and streaming to Next.js
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::get('/messages', [MessageController::class, 'index']); // Get messages for conversation
    Route::get('/messages/{message}', [MessageController::class, 'show']);
    Route::put('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
});
