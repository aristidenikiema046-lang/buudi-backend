<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\v1\DriverRegisterController;
use App\Http\Controllers\Api\v1\DriverProfileController;
use App\Http\Controllers\Api\v1\DriverRideController;
use App\Http\Controllers\Api\v1\Admin\AdminDriverController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- CLIENT (Inscription par e-mail avec OTP) ---
Route::post('/client/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/client/verify-register', [AuthController::class, 'verifyAndRegisterClient']);
Route::post('/client/send-email-otp', [AuthController::class, 'sendOtp']);

// ==========================================
// ALIAS DE COMPATIBILITÉ POUR FLUTTER (Sans /v1)
// ==========================================
Route::middleware('auth:api')->group(function () {
    Route::get('/driver/profile', [DriverProfileController::class, 'show']);
    Route::post('/driver/toggle-status', [DriverProfileController::class, 'toggleOnlineStatus']);
    Route::post('/driver/buy-pass', [DriverProfileController::class, 'buyPass']);

    // --- COURSES & DASHBOARD (ALIAS) ---
    Route::get('/driver/dashboard', [DriverRideController::class, 'getDashboard']);
    Route::get('/driver/active-ride', [DriverRideController::class, 'getActiveRide']);
    Route::post('/driver/rides/{id}/accept', [DriverRideController::class, 'acceptRide']);
    Route::post('/driver/rides/{id}/arrive', [DriverRideController::class, 'arriveAtPickup']);
    Route::post('/driver/rides/{id}/start', [DriverRideController::class, 'startRide']);
    Route::post('/driver/rides/{id}/complete', [DriverRideController::class, 'completeRide']);
});

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
    });
});