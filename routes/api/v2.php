<?php

use App\Http\Controllers\Api\V2\CompleteTaskController;
use App\Http\Controllers\Api\V2\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    Route::apiResource('/tasks', TaskController::class);
    Route::patch('/tasks/{task}/complete', CompleteTaskController::class);
});

// Rate limiting for API v2
// Route::middleware(['throttle:api'])->group(function () {
//
// });