<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;

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

        // User routes (will be added in next phase)
        // Route::apiResource('users', UserController::class);

        // Conversation routes (will be added in next phase)
        // Route::apiResource('conversations', ConversationController::class);

        // Message routes (will be added in next phase)
        // Route::apiResource('messages', MessageController::class);
    });
});
