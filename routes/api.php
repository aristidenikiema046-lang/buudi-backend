<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\v1\DriverRegisterController;
use App\Http\Controllers\Api\v1\DriverProfileController;
use App\Http\Controllers\Api\v1\DriverRideController;
use App\Http\Controllers\Api\v1\MessageController;
use App\Http\Controllers\Api\v1\NotificationController;
use App\Http\Controllers\Api\v1\MerchantRegisterController;
use App\Http\Controllers\Api\v1\Admin\AdminDriverController;
use App\Http\Controllers\Api\v1\Admin\AdminMerchantController;
use App\Http\Controllers\Api\v1\Client\WalletController;
use App\Http\Controllers\Api\v1\Client\TransferController;
use App\Http\Controllers\Api\v1\Client\RideController;
use App\Http\Controllers\Api\v1\Client\ProfileController;
use App\Http\Controllers\Api\v1\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Api\v1\Merchant\WalletController as MerchantWalletController;
use App\Http\Controllers\Api\v1\Merchant\ProfileController as MerchantProfileController;
use App\Http\Controllers\Api\v1\Merchant\PaymentRequestController as MerchantPaymentRequestController;
use App\Http\Controllers\Api\v1\Merchant\ProductController as MerchantProductController;
use App\Http\Controllers\Api\v1\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Api\v1\PaymentRequestController;
use App\Http\Controllers\Api\v1\SupermarketController;
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

    // --- COMMERÇANTS (Inscription publique) ---
    Route::post('/merchant/register', [MerchantRegisterController::class, 'register']);

    // --- ROUTES PROTÉGÉES PAR JWT ---
    Route::middleware('auth:api')->group(function () {
        
        // Profil, Statut du chauffeur & Pass (rôle "driver" uniquement)
        Route::get('/driver/profile', [DriverProfileController::class, 'show'])->middleware('role:driver');
        Route::get('/driver/status', [DriverProfileController::class, 'checkStatus'])->middleware('role:driver');
        Route::post('/driver/toggle-status', [DriverProfileController::class, 'toggleOnlineStatus'])->middleware('role:driver');
        Route::post('/driver/buy-pass', [DriverProfileController::class, 'buyPass'])->middleware('role:driver');

        // --- DASHBOARD & COURSES (v1) — rôle "driver" uniquement ---
        Route::get('/driver/dashboard', [DriverRideController::class, 'getDashboard'])->middleware('role:driver');
        Route::get('/driver/active-ride', [DriverRideController::class, 'getActiveRide'])->middleware('role:driver');
        Route::get('/driver/rides/pending', [DriverRideController::class, 'getPendingRides'])->middleware('role:driver');
        Route::post('/driver/rides/{id}/accept', [DriverRideController::class, 'acceptRide'])->middleware('role:driver');
        Route::post('/driver/rides/{id}/arrive', [DriverRideController::class, 'arriveAtPickup'])->middleware('role:driver');
        Route::post('/driver/rides/{id}/start', [DriverRideController::class, 'startRide'])->middleware('role:driver');
        Route::post('/driver/rides/{id}/complete', [DriverRideController::class, 'completeRide'])->middleware('role:driver');
        Route::post('/driver/rides/{id}/cancel', [DriverRideController::class, 'cancelRide'])->middleware('role:driver');

        // Mise à jour Token FCM
        Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);

        // --- MESSAGERIE CLIENT ↔ CHAUFFEUR/LIVREUR liée à une course ---
        // Pas de middleware role:... : client ET driver doivent tous les
        // deux y accéder, le contrôle d'accès se fait dans le contrôleur
        // (seuls passenger_id/driver_id de la course peuvent lire/écrire).
        Route::post('/rides/{ride}/messages', [MessageController::class, 'store']);
        Route::get('/rides/{ride}/messages', [MessageController::class, 'index']);

        // --- NOTIFICATIONS IN-APP — communes à tous les rôles connectés,
        // pas de middleware role:... (client, driver, merchant en ont tous) ---
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // --- ADMINISTRATION PROTÉGÉE (rôle "admin" uniquement — ajouté ici,
        // couvrait auparavant seulement auth:api, n'importe quel compte
        // connecté pouvait donc appeler ces routes) ---
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/drivers/pending', [AdminDriverController::class, 'getPendingDrivers']);
            Route::post('/drivers/{id}/status', [AdminDriverController::class, 'updateStatus']);

            Route::get('/merchants/pending', [AdminMerchantController::class, 'getPendingMerchants']);
            Route::post('/merchants/{id}/status', [AdminMerchantController::class, 'updateStatus']);
        });

        // --- CLIENT : PORTEFEUILLE & TRANSFERTS (rôle "client" uniquement) ---
        Route::prefix('client')->middleware('role:client')->group(function () {
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
            Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

            Route::post('/transfer', [TransferController::class, 'transfer']);

            Route::post('/rides', [RideController::class, 'store']);
            Route::get('/rides/{id}', [RideController::class, 'show']);
            Route::post('/rides/{id}/cancel', [RideController::class, 'cancel']);

            // Commande Supermarché — la course de livraison n'est PAS créée
            // ici : elle naît plus tard, côté serveur, quand le supermarché
            // confirme (voir Merchant\OrderController::confirm).
            Route::post('/orders', [ClientOrderController::class, 'store']);

            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
        });

        // --- COMMERÇANT : PORTEFEUILLE & PROFIL (rôle "merchant" uniquement) ---
        Route::prefix('merchant')->middleware('role:merchant')->group(function () {
            Route::get('/wallet', [MerchantWalletController::class, 'show']);
            Route::get('/wallet/transactions', [MerchantWalletController::class, 'transactions']);

            Route::get('/profile', [MerchantProfileController::class, 'show']);
            Route::put('/profile', [MerchantProfileController::class, 'update']);

            Route::get('/payment-requests', [MerchantPaymentRequestController::class, 'index']);
            Route::post('/payment-requests', [MerchantPaymentRequestController::class, 'store']);

            // Catalogue Supermarché — réservé aux comptes avec
            // is_supermarket=true (garde-fou dans les contrôleurs eux-mêmes,
            // pas seulement au niveau des routes).
            Route::get('/products', [MerchantProductController::class, 'index']);
            Route::post('/products', [MerchantProductController::class, 'store']);
            Route::put('/products/{id}', [MerchantProductController::class, 'update']);

            Route::get('/orders', [MerchantOrderController::class, 'index']);
            Route::post('/orders/{id}/confirm', [MerchantOrderController::class, 'confirm']);
        });

        // Paiement wallet-à-wallet d'une demande de paiement — pas sous
        // /client/... par choix : l'URL suit la ressource publique
        // /v1/payment-requests/{token}, seule l'action d'écriture exige
        // d'être connecté en tant que client.
        Route::post('/payment-requests/{token}/pay-with-wallet', [PaymentRequestController::class, 'payWithWallet'])
            ->middleware('role:client');
    });

    // --- DEMANDE DE PAIEMENT : consultation publique, sans auth (le lien est
    // partagé au payeur final, qui n'a pas forcément de compte Buudi) ---
    Route::get('/payment-requests/{token}', [PaymentRequestController::class, 'show']);

    // --- SUPERMARCHÉS : catalogue public, consultable avant connexion,
    // même logique que /payment-requests/{token} ci-dessus ---
    Route::get('/supermarkets', [SupermarketController::class, 'index']);
    Route::get('/supermarkets/{id}/products', [SupermarketController::class, 'products']);
});

// ==========================================
// WEBHOOKS PUBLICS (appelés par les fournisseurs de paiement, pas par Flutter)
// ==========================================
Route::prefix('v1/webhooks')->group(function () {
    Route::post('/mobile-money/{provider}', [WebhookController::class, 'handle']);
});