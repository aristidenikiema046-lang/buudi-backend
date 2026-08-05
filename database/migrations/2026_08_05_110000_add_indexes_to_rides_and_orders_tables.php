<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Aucun index n'existait au-delà des clés primaires sur ces deux tables
     * (vérifié directement en base) — foreignUuid()->constrained() ne crée
     * pas d'index sur la colonne référençante en Postgres, seulement la
     * contrainte FK. Nécessaire pour que les filtres du panel Filament
     * (RideResource/OrderResource : statut, service_type, chauffeur,
     * marchand, plage de dates) restent efficaces une fois la table remplie
     * — coût quasi nul aujourd'hui vu le volume (tables vides).
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->index('status');
            $table->index('service_type');
            $table->index('driver_id');
            $table->index('passenger_id');
            $table->index('created_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('merchant_profile_id');
            $table->index('client_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['service_type']);
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['passenger_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['merchant_profile_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
