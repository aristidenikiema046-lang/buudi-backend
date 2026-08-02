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
            // Pourcentage pris par Buudi sur les ventes du commerçant (même
            // logique que la commission de 15% déjà appliquée aux chauffeurs).
            $table->decimal('commission_rate', 5, 2)->nullable()->default(5.00)->after('status');
            // Logo/photo de la boutique, affiché sur le profil. Pas un document
            // d'identité — juste l'équivalent visuel du profil commerçant.
            $table->string('logo_url')->nullable()->after('business_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'logo_url']);
        });
    }
};
