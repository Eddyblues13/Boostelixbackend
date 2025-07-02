<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\Admin\AdminAuthController;




Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);




// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [UserController::class, 'user']);
    Route::get('/user/logout', [UserController::class, 'logout']);
    // Categories endpoint
    Route::get('/categories', [CategoryController::class, 'index']);

    // Services endpoint
    Route::get('/services', [ServiceController::class, 'index']);
});



Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.token'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
    });
});
