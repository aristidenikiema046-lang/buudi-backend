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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();

            // Valeur réelle de users.role au moment de l'envoi ('client' ou
            // 'driver') — pas de mapping vers un vocabulaire "partner" qui
            // n'existe nulle part ailleurs dans le backend.
            $table->string('sender_role');

            $table->text('content');

            // Pas de updated_at : un message n'est jamais modifié après
            // envoi. Voir Message::UPDATED_AT = null côté modèle.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
