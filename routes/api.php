<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
 */

// Include auth routes
require __DIR__ . '/auth.php';
// Include versioned API routes
require __DIR__ . '/api/v1.php';
// Protected routes requiring both Sanctum and JWT authentication
Route::middleware(['auth:sanctum', 'jwt.verify', 'verify.token'])->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'message' => 'User retrieved successfully',
        ]);
    });

    require __DIR__ . '/api/v2.php';
});
