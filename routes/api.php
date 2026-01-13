<?php

use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\ApiResurceController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Hikvision Webhook Routes
Route::post('webhook/events', [WebhookController::class, 'receiveEvents']);
Route::post('webhook/events/batch', [WebhookController::class, 'receiveEvents']);
Route::post('webhook/event', [WebhookController::class, 'receiveEvent']);
Route::post('webhook/event/batch', [WebhookController::class, 'receiveEvents']);
Route::get('webhook/health', [WebhookController::class, 'health']);

Route::get('api/{model}', [ApiResurceController::class, 'index']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('ajax', function (Request $r) {

    $_model = trim($r->get('model'));
    $conditions = [];
    foreach ($_GET as $key => $v) {
        if (substr($key, 0, 6) != 'query_') {
            continue;
        }
        $_key = str_replace('query_', "", $key);
        $conditions[$_key] = $v;
    }

    if (strlen($_model) < 2) {
        return [
            'data' => []
        ];
    }

    $model = "App\Models\\" . $_model;
    $search_by_1 = trim($r->get('search_by_1'));
    $search_by_2 = trim($r->get('search_by_2'));

    $q = trim($r->get('q'));

    $res_1 = $model::where(
        $search_by_1,
        'like',
        "%$q%"
    )
        ->where($conditions)
        ->limit(20)->get();
    $res_2 = [];

    if ((count($res_1) < 20) && (strlen($search_by_2) > 1)) {
        $res_2 = $model::where(
            $search_by_2,
            'like',
            "%$q%"
        )
            ->where($conditions)
            ->limit(20)->get();
    }

    $data = [];
    foreach ($res_1 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }
    foreach ($res_2 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }

    return [
        'data' => $data
    ];
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
