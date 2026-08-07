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
            // Moyenne mise en cache plutôt que calculée à la volée (AVG à
            // chaque lecture de profil, qui est un chemin chaud) — recalculée
            // à chaque nouvel avis (chemin rare). null tant qu'aucun avis
            // n'existe : pas de note fictive par défaut.
            $table->decimal('rating_average', 3, 2)->nullable()->after('last_location_at');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_average');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['rating_average', 'rating_count']);
        });
    }
};
