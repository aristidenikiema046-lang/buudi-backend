<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            // Distingue un supermarché partenaire (catalogue géré par Buudi
            // ou par lui via buudi_merchant_app) d'un commerçant classique
            // (paiement QR à montant unique, pas de catalogue). Un compte
            // peut rester un commerçant ordinaire par défaut.
            $table->boolean('is_supermarket')->default(false)->after('business_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            $table->dropColumn('is_supermarket');
        });
    }
};
