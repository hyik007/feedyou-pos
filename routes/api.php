<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;

/*
| REST API v1  (prefix: /api/v1)
| Public endpoints serve the QR ordering page; everything else needs staff auth
| (session on staff pages, or X-Staff-Token header for API clients / devices).
*/
Route::prefix('v1')->group(function () {
    // Public
    Route::get('menu', [MenuController::class, 'index']);
    Route::post('orders/quote', [OrderController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');   // QR (table_token) or staff
    Route::get('orders/{orderNo}/track', [OrderController::class, 'track']);
    Route::post('ai/parse-order', [AiController::class, 'parseOrder'])->middleware('throttle:20,1');

    // Staff
    Route::middleware('staff')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/pay', [OrderController::class, 'pay']);

        Route::get('tables', [TableController::class, 'index']);

        Route::post('menu/items', [MenuController::class, 'store']);
        Route::patch('menu/items/{item}', [MenuController::class, 'update']);
        Route::patch('menu/items/{item}/availability', [MenuController::class, 'availability']);

        Route::get('reports/daily', [ReportController::class, 'daily']);
        Route::get('ai/insights', [AiController::class, 'insights']);
    });
});
