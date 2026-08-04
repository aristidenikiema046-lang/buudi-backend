<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajout non demandé explicitement mais nécessaire : merchant_profiles
     * n'avait qu'une adresse texte (business_address), pas de coordonnées.
     * La création de la course de livraison Supermarché (Ride) a besoin
     * d'un pickup_latitude/pickup_longitude numérique — les colonnes de
     * rides sont NOT NULL, sans ça Merchant\OrderController::confirm()
     * échouerait systématiquement pour tout supermarché.
     */
    public function up(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            $table->double('business_latitude')->nullable()->after('business_address');
            $table->double('business_longitude')->nullable()->after('business_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            $table->dropColumn(['business_latitude', 'business_longitude']);
        });
    }
};
