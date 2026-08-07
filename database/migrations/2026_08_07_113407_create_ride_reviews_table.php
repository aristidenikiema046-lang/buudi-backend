<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ride_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // unique() : une seule ligne par course, c'est la vraie garantie
            // "un avis par course" (le contrôleur vérifie aussi explicitement
            // avant insertion pour un message propre, mais c'est cette
            // contrainte qui protège réellement contre la concurrence).
            $table->foreignUuid('ride_id')->unique()->constrained('rides')->cascadeOnDelete();

            // Dénormalisé depuis ride.driver_id au moment de la création
            // (jamais fourni par le client) — évite une jointure vers rides
            // pour la requête d'agrégation (AVG/COUNT par chauffeur) qui,
            // elle, tourne à chaque nouvel avis.
            $table->foreignUuid('reviewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            // Pas d'updated_at : un avis n'est jamais modifié une fois posté,
            // même convention que la table notifications.
            $table->timestamp('created_at')->useCurrent();

            $table->index('reviewed_user_id');
        });

        // Contrainte CHECK en base, pas seulement une validation applicative
        // (voir l'incident commission_amount : la validation Laravel seule
        // n'avait pas suffi à garantir l'intégrité des données).
        DB::statement('ALTER TABLE ride_reviews ADD CONSTRAINT ride_reviews_rating_between_1_and_5 CHECK (rating BETWEEN 1 AND 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_reviews');
    }
};
