<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\v1\DriverRegisterController;
use App\Http\Controllers\Api\v1\DriverProfileController;
use App\Http\Controllers\Api\v1\DriverRideController;
use App\Http\Controllers\Api\v1\Admin\AdminDriverController;
use App\Http\Controllers\Api\v1\Client\WalletController;
use App\Http\Controllers\Api\v1\Client\TransferController;
use App\Http\Controllers\Api\v1\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::post('/login', [AuthController::class, 'login']);

// --- CLIENT (Inscription par e-mail avec OTP) ---
Route::post('/client/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/client/verify-register', [AuthController::class, 'verifyAndRegisterClient']);
Route::post('/client/send-email-otp', [AuthController::class, 'sendOtp']);

// ==========================================
// (Les anciens alias "sans /v1" pour le chauffeur ont été supprimés le
// 2026-07-31 : ils dupliquaient exactement les routes /v1/driver/... ci-dessous.
// Liste de ce qui a été retiré, pour vérifier côté Flutter que plus rien
// n'appelle ces chemins : GET api/driver/profile, POST api/driver/toggle-status,
// POST api/driver/buy-pass, GET api/driver/dashboard, GET api/driver/active-ride,
// POST api/driver/rides/{id}/accept, POST api/driver/rides/{id}/arrive,
// POST api/driver/rides/{id}/start, POST api/driver/rides/{id}/complete.
// Utilisez désormais uniquement les chemins /v1/driver/... )
// ==========================================
// ROUTES API VERSION 1 (v1)
// ==========================================
Route::prefix('v1')->group(function () {
    
    // --- CHAUFFEURS (Inscription publique) ---
    Route::post('/driver/register', [DriverRegisterController::class, 'register']);

    // --- ROUTES PROTÉGÉES PAR JWT ---
    Route::middleware('auth:api')->group(function () {
        
        // Profil, Statut du chauffeur & Pass
        Route::get('/driver/profile', [DriverProfileController::class, 'show']);
        Route::get('/driver/status', [DriverProfileController::class, 'checkStatus']);
        Route::post('/driver/toggle-status', [DriverProfileController::class, 'toggleOnlineStatus']);
        Route::post('/driver/buy-pass', [DriverProfileController::class, 'buyPass']);

        // --- DASHBOARD & COURSES (v1) ---
        Route::get('/driver/dashboard', [DriverRideController::class, 'getDashboard']);
        Route::get('/driver/active-ride', [DriverRideController::class, 'getActiveRide']);
        Route::post('/driver/rides/{id}/accept', [DriverRideController::class, 'acceptRide']);
        Route::post('/driver/rides/{id}/arrive', [DriverRideController::class, 'arriveAtPickup']);
        Route::post('/driver/rides/{id}/start', [DriverRideController::class, 'startRide']);
        Route::post('/driver/rides/{id}/complete', [DriverRideController::class, 'completeRide']);

        // Mise à jour Token FCM
        Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
        
        // --- ADMINISTRATION PROTÉGÉE ---
        Route::prefix('admin')->group(function () {
            Route::get('/drivers/pending', [AdminDriverController::class, 'getPendingDrivers']);
            Route::post('/drivers/{id}/status', [AdminDriverController::class, 'updateStatus']);
        });

        // --- CLIENT : PORTEFEUILLE & TRANSFERTS (rôle "client" uniquement) ---
        Route::prefix('client')->middleware('role:client')->group(function () {
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
            Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

            Route::post('/transfer', [TransferController::class, 'transfer']);
        });
    });
});

// ==========================================
// WEBHOOKS PUBLICS (appelés par les opérateurs mobile money, pas par Flutter)
// ==========================================
Route::prefix('v1/webhooks')->group(function () {
    Route::post('/mobile-money/{operator}', [WebhookController::class, 'handle']);
});