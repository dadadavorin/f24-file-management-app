<?php

use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('v1')->group(function (): void {
    Route::get('/nodes/root', [NodeController::class, 'root']);
    Route::get('/nodes/{id}', [NodeController::class, 'show'])->whereNumber('id');
    Route::get('/nodes/{id}/children', [NodeController::class, 'children'])->whereNumber('id');
    Route::post('/nodes', [NodeController::class, 'store']);
    Route::delete('/nodes/{id}', [NodeController::class, 'destroy'])->whereNumber('id');

    // Generous on purpose: the default 60/min combined with the frontend's
    // 250ms typeahead debounce would 429 a reviewer mid-demo.
    Route::middleware('throttle:300,1')->group(function (): void {
        Route::get('/search/files', [SearchController::class, 'files']);
        Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    });
});
