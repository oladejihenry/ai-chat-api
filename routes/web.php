<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

//Google auth
Route::prefix('auth/google')->group(function () {
    Route::get('url', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

require __DIR__ . '/auth.php';
