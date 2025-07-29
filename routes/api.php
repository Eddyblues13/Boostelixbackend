<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\API\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\ManageUserController;
use App\Http\Controllers\API\Admin\ApiProviderController;
use App\Http\Controllers\Api\Admin\ManageServiceController;
use App\Http\Controllers\Api\Admin\ManageCategoryController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::get('/all-services', [ServiceController::class, 'allServices']);




// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [UserController::class, 'user']);
    Route::get('/user/logout', [UserController::class, 'logout']);
    // Categories endpoint
    Route::get('/categories', [CategoryController::class, 'index']);

    // Services endpoint
    Route::get('/services', [ServiceController::class, 'index']);

    // orders endpoint
    Route::post('/orders', [OrderController::class, 'store']);
});




Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.token'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('dashboard', [AdminController::class, 'dashboard']);

        // Category management
        Route::post('/categories', [ManageCategoryController::class, 'store']);
        Route::put('/categories/{id}', [ManageCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [ManageCategoryController::class, 'destroy']);
        Route::post('/categories/{id}/activate', [ManageCategoryController::class, 'activate']);
        Route::post('/categories/{id}/deactivate', [ManageCategoryController::class, 'deactivate']);
        Route::post('/categories/deactivate-multiple', [ManageCategoryController::class, 'deactivateMultiple']);

        // Service management
        Route::post('/services', [ManageServiceController::class, 'store']);
        Route::put('/services/{id}', [ManageServiceController::class, 'update']);
        Route::delete('/services/{id}', [ManageServiceController::class, 'destroy']);
        Route::post('/services/{id}/activate', [ManageServiceController::class, 'activate']);
        Route::post('/services/{id}/deactivate', [ManageServiceController::class, 'deactivate']);
        Route::post('/services/deactivate-multiple', [ManageServiceController::class, 'deactivateMultiple']);

        // api providers
        Route::prefix('providers')->group(function () {
            Route::apiResource('/', ApiProviderController::class);
            Route::patch('/{id}/toggle-status', [ApiProviderController::class, 'toggleStatus']);
            Route::post('/{id}/sync-services', [ApiProviderController::class, 'syncServices']);

            // Route::get('/api-providers', [ApiProviderController::class, 'index']);
            // Route::post('/api-providers', [ApiProviderController::class, 'store']);
            // Route::post('/api-provider/services', [ApiProviderController::class, 'getApiServices']);
            Route::post('/services/import', [ApiProviderController::class, 'import']);
            Route::post('/services/import-bulk', [ApiProviderController::class, 'importMulti']);
        });
    });
});









Route::prefix('admin/users')->middleware(['auth:sanctum', 'admin.token'])->group(function () {

    // User management
    Route::get('/', [ManageUserController::class, 'index']);
    Route::get('/{id}', [ManageUserController::class, 'show']);
    Route::post('/{id}/send-email', [ManageUserController::class, 'sendEmail']);
    Route::post('/balance-adjust', [ManageUserController::class, 'adjust']);
    Route::post('/{id}/adjust-balance', [ManageUserController::class, 'adjustBalance']);
    Route::post('/{id}/custom-rate', [ManageUserController::class, 'setCustomRate']);



    // Order management
    Route::get('/{id}/orders', [ManageUserController::class, 'getUserOrders']);
    Route::post('/{id}/orders', [ManageUserController::class, 'createUserOrder']);
    Route::put('/{userId}/orders/{orderId}', [ManageUserController::class, 'updateUserOrder']);
    Route::delete('/{userId}/orders/{orderId}', [ManageUserController::class, 'deleteUserOrder']);

    // Transaction management
    Route::get('/{id}/transactions', [ManageUserController::class, 'getUserTransactions']);
    Route::post('/{id}/transactions', [ManageUserController::class, 'createUserTransaction']);
    Route::put('/{userId}/transactions/{transactionId}', [ManageUserController::class, 'updateUserTransaction']);
    Route::delete('/{userId}/transactions/{transactionId}', [ManageUserController::class, 'deleteUserTransaction']);
});
