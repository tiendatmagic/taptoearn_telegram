<?php

use App\Http\Controllers\Api\TapGameController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:taptoearn')->group(function (): void {
    Route::post('/players/sync', [TapGameController::class, 'syncPlayer']);
    Route::post('/players/state', [TapGameController::class, 'state']);
    Route::post('/tap', [TapGameController::class, 'tap']);
});
