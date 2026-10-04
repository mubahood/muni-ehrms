<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Hikvision Webhook Routes
Route::post('webhook/events', [WebhookController::class, 'receiveEvents']);
Route::post('webhook/events/batch', [WebhookController::class, 'receiveEvents']);
Route::post('webhook/event', [WebhookController::class, 'receiveEvent']);
Route::post('webhook/event/batch', [WebhookController::class, 'receiveEvents']);
Route::get('webhook/health', [WebhookController::class, 'health']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// =============================================================================
// HIKVISION WEBHOOK ROUTES
// =============================================================================
use App\Http\Controllers\Api\HikvisionWebhookController;

// Public endpoint - no authentication
Route::get('hikvision/ping', [HikvisionWebhookController::class, 'ping']);

// Token-authenticated endpoints
Route::prefix('hikvision')->group(function () {
    Route::post('events', [HikvisionWebhookController::class, 'receiveEvent']);
    Route::post('events/batch', [HikvisionWebhookController::class, 'receiveBatch']);
    Route::get('status', [HikvisionWebhookController::class, 'status']);
});
