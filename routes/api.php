<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\AffiliateController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\API\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\ManageUserController;
use App\Http\Controllers\API\Admin\ApiProviderController;
use App\Http\Controllers\Api\Admin\ManageCategoryController;
use App\Http\Controllers\Api\Admin\ManageOrderController;
use App\Http\Controllers\API\Admin\TransactionController;
use App\Http\Controllers\API\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\ManageServiceController;
use App\Http\Controllers\Api\Admin\ManageTicketController;
use App\Http\Controllers\Api\Admin\ManageTransactionsController;
use App\Http\Controllers\API\CurrencyController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::get('/all-services', [ServiceController::class, 'allServices']);
Route::get('/currencies', [CurrencyController::class, 'fetchCurrencies']);




// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [UserController::class, 'user']);
    Route::get('/user/logout', [UserController::class, 'logout']);
    // Categories endpoint
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/all-smm-categories', [CategoryController::class, 'allSmmCategories']);

    // Services endpoint
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/all-smm-services', [ServiceController::class, 'allSmmServices']);
    Route::get('/updates', [ServiceController::class, 'serviceUpdates']);

    // orders endpoint
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/history', [OrderController::class, 'history']);

     // Ticket endpoint
    Route::post('/tickets', [TicketController::class, 'store']);

       // Ticket History endpoint
    Route::get('/ticketshistory', [TicketController::class, 'index']);

    // Payment endpoints
    Route::post('/payment/initiate', [PaymentController::class, 'initiatePayment']);
    Route::get('/payment/history', [PaymentController::class, 'paymentHistory']);







    // Account routes
    Route::get('/account', [AccountController::class, 'getAccount']);
    Route::get('/account/notifications', [AccountController::class, 'getNotifications']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::put('/account/email', [AccountController::class, 'updateEmail']);
    Route::put('/account/username', [AccountController::class, 'updateUsername']);
    Route::put('/account/two-factor', [AccountController::class, 'updateTwoFactor']);
    Route::post('/account/api-key', [AccountController::class, 'generateApiKey']);
    Route::put('/account/preferences', [AccountController::class, 'updatePreferences']);
    Route::put('/account/notifications', [AccountController::class, 'updateNotifications']);



    // Affiliate Program Routes
    Route::get('/affiliate', [AffiliateController::class, 'index']);
    Route::post('/affiliate/generate-link', [AffiliateController::class, 'generateLink']);

    // Affiliate Stats
    Route::get('/affiliate/stats', [AffiliateController::class, 'getStats']);

    // Payouts
    Route::get('/affiliate/payouts', [AffiliateController::class, 'getPayouts']);
    Route::post('/affiliate/request-payout', [AffiliateController::class, 'requestPayout']);

    // Track referral visits
    Route::get('/affiliate/track/{code}', [AffiliateController::class, 'trackVisit'])->withoutMiddleware(['auth:sanctum']);
});

// Callback route (no auth needed)
Route::get('/payment/callback', [PaymentController::class, 'paymentCallback']);




Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.token'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('dashboard', [AdminController::class, 'dashboard']);

        // Admin Settings Endpoints
        Route::get('/settings', [AdminSettingsController::class, 'index']);
        Route::put('/settings/profile', [AdminSettingsController::class, 'updateProfile']);
        Route::put('/settings/security', [AdminSettingsController::class, 'updateSecurity']);
        Route::get('/settings/activity', [AdminSettingsController::class, 'getActivityLogs']);

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
        Route::get('/orders', [ManageOrderController::class, 'allOrders']);


        // api providers
        Route::prefix('providers')->group(function () {

            Route::get('', [ApiProviderController::class, 'index']);
            Route::post('', [ApiProviderController::class, 'store']);
            Route::get('{id}', [ApiProviderController::class, 'show']);
            Route::put('{id}', [ApiProviderController::class, 'update']);
            Route::delete('{id}', [ApiProviderController::class, 'destroy']);

            Route::patch('/{id}/toggle-status', [ApiProviderController::class, 'toggleStatus']);
            Route::post('/{id}/sync-services', [ApiProviderController::class, 'syncServices']);

            Route::post('/api-provider/services', [ApiProviderController::class, 'getApiServices']);
            Route::post('/services/import', [ApiProviderController::class, 'import']);
            Route::post('/services/import-bulk', [ApiProviderController::class, 'importMulti']);
            Route::post('/services/all', [ApiProviderController::class, 'fetchAllServicesFromProvider']);
            Route::post('/services/save', [ApiProviderController::class, 'importServices']);
        });

           // Manageusertickets
           Route::get('/tickets', [ManageTicketController::class, 'index']);
           Route::get('/tickets/{id}', [ManageTicketController::class, 'show']);
           Route::put('/tickets/{id}/status', [ManageTicketController::class, 'updateStatus']);
           Route::delete('/tickets/{id}', [ManageTicketController::class, 'destroy']);
        // Transaction Management Routes
        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::get('/stats', [TransactionController::class, 'stats']);
            Route::post('/', [TransactionController::class, 'store']);
            Route::get('/{transaction}', [TransactionController::class, 'show']);
            Route::put('/{transaction}', [TransactionController::class, 'update']);
            Route::delete('/{transaction}', [TransactionController::class, 'destroy']);
            Route::patch('/{transaction}/status', [TransactionController::class, 'changeStatus']);
        });
    });
});









Route::prefix('admin/users')->middleware(['auth:sanctum', 'admin.token'])->group(function () {

    // User management
    Route::get('/', [ManageUserController::class, 'index']);
    Route::get('/{id}', [ManageUserController::class, 'show']);
    Route::post('/{id}/send-email', [ManageUserController::class, 'sendEmail']);
    Route::post('/balance-adjust', [ManageUserController::class, 'adjust']);
    Route::get('/{id}/orders', [ManageUserController::class, 'getUserOrders']);
    Route::post('/{id}/adjust-balance', [ManageUserController::class, 'adjustBalance']);
    Route::post('/{id}/custom-rate', [ManageUserController::class, 'setCustomRate']);




    // Order management
    Route::get('/{id}/orders', [ManageUserController::class, 'getUserOrders']);
    Route::delete('/orders/{id}', [ManageOrderController::class, 'destroy']);
    Route::put('/orders/{id}', [ManageOrderController::class, 'update']);
    Route::patch('/orders/{id}/status', [ManageOrderController::class, 'updateStatus']);
    Route::get('/categories', [ManageOrderController::class, 'getUserCategories']);
    Route::get('/services', [ManageOrderController::class, 'getUserServices']);


    // Transaction management
    Route::get('/{id}/transactions', [ManageTransactionsController::class, 'getUserTransactions']);
    Route::post('/{id}/transactions', [ManageUserController::class, 'createUserTransaction']);
    Route::put('/{userId}/transactions/{transactionId}', [ManageUserController::class, 'updateUserTransaction']);
    Route::delete('/{userId}/transactions/{transactionId}', [ManageUserController::class, 'deleteUserTransaction']);
});
