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
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Dernière position connue uniquement (pas d'historique) — alimente
            // la Phase 3 (cascade de dispatch par proximité). decimal(10,7) :
            // 3 chiffres avant la virgule (longitude jusqu'à ±180) et 7 après
            // (~1cm de précision), largement suffisant pour du GPS mobile.
            $table->decimal('last_latitude', 10, 7)->nullable()->after('vehicle_image_url');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->timestamp('last_location_at')->nullable()->after('last_longitude');

            // Sert la requête de fraîcheur que la Phase 3 fera à chaque
            // dispatch ("where last_location_at > now() - N secondes") —
            // ajouté dès maintenant car gratuit sur une table quasi vide,
            // pas parce que le volume actuel l'exige.
            $table->index('last_location_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['last_location_at']);
            $table->dropColumn(['last_latitude', 'last_longitude', 'last_location_at']);
        });
    }
};
