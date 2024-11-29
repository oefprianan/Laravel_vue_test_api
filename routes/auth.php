<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Public routes
    Route::middleware('guest')->group(function () {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'jwt.verify', 'verify.token'])->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::post('/refresh', RefreshTokenController::class);
    });
});
