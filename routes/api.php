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
        Route::get('/api-providers', [ApiProviderController::class, 'index']);
        Route::post('/api-providers', [ApiProviderController::class, 'store']);
        Route::post('/api-provider/services', [ApiProviderController::class, 'getApiServices']);
        Route::post('/services/import', [ApiProviderController::class, 'import']);
        Route::post('/services/import-bulk', [ApiProviderController::class, 'importMulti']);
    });
});





Route::prefix('admin/users')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // User management
    Route::get('/', [ManageUserController::class, 'index']);
    Route::get('/{id}', [ManageUserController::class, 'show']);
    Route::put('/{id}', [ManageUserController::class, 'update']);
    Route::post('/{id}/login', [ManageUserController::class, 'loginAsUser']);
    Route::post('/{id}/add-balance', [ManageUserController::class, 'addBalance']);
    Route::post('/{id}/reduce-balance', [ManageUserController::class, 'reduceBalance']);
    Route::post('/{id}/activate', [ManageUserController::class, 'activate']);
    Route::post('/{id}/deactivate', [ManageUserController::class, 'deactivate']);
    Route::post('/{id}/change-status', [ManageUserController::class, 'changeStatus']);
    Route::post('/{id}/generate-api-key', [ManageUserController::class, 'generateApiKey']);
    Route::post('/{id}/send-email', [ManageUserController::class, 'sendUserEmail']);
    Route::post('/send-bulk-email', [ManageUserController::class, 'sendBulkEmail']);

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
