<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TypingController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ConversationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API version prefix
Route::prefix('v1')->group(function () {

    // Public routes (no authentication required)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {

        // Authentication routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::delete('/account', [AuthController::class, 'deleteAccount']);
        });

        // Profile routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::post('/', [ProfileController::class, 'update']);
            Route::post('/status', [ProfileController::class, 'updateStatus']);
            Route::delete('/avatar', [ProfileController::class, 'removeAvatar']);
            Route::post('/push-subscription', [ProfileController::class, 'updatePushSubscription']);
        });

        // User routes
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/search', [UserController::class, 'search']);
            Route::get('/online', [UserController::class, 'online']);
            Route::post('/heartbeat', [UserController::class, 'heartbeat']);
            Route::get('/{user}', [UserController::class, 'show']);
        });

        // Conversation routes
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index']);
            Route::post('/', [ConversationController::class, 'store']);
            Route::get('/{conversation}', [ConversationController::class, 'show']);
            Route::post('/{conversation}', [ConversationController::class, 'update']);
            Route::delete('/{conversation}', [ConversationController::class, 'destroy']);

            // Group management
            Route::post('/{conversation}/add-user', [ConversationController::class, 'addUser']);
            Route::post('/{conversation}/remove-user', [ConversationController::class, 'removeUser']);
            Route::post('/{conversation}/make-admin', [ConversationController::class, 'makeAdmin']);

            // Conversation settings
            Route::post('/{conversation}/toggle-mute', [ConversationController::class, 'toggleMute']);
            Route::post('/{conversation}/toggle-archive', [ConversationController::class, 'toggleArchive']);

            // Messages in conversation
            Route::get('/{conversation}/messages', [MessageController::class, 'index']);

            // Typing indicators
            Route::post('/{conversation}/typing', [TypingController::class, 'typing']);
            Route::post('/{conversation}/stop-typing', [TypingController::class, 'stopTyping']);
            Route::get('/{conversation}/typing-users', [TypingController::class, 'getCurrentlyTyping']);
        });

        // Message routes
        Route::prefix('messages')->group(function () {
            Route::post('/', [MessageController::class, 'store']);
            Route::get('/unread-count', [MessageController::class, 'unreadCount']);
            Route::post('/mark-as-read', [MessageController::class, 'markAsRead']);
            Route::get('/{message}', [MessageController::class, 'show']);
            Route::put('/{message}', [MessageController::class, 'update']);
            Route::delete('/{message}', [MessageController::class, 'destroy']);
            Route::post('/{message}/reaction', [MessageController::class, 'toggleReaction']);
        });
    });
});
