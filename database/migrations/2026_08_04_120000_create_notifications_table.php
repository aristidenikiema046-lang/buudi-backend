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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // 'ride_status_changed' | 'new_message' | 'wallet_transaction' |
            // 'account_status_changed'
            $table->string('type');

            $table->string('title');
            $table->string('body');

            // Contexte spécifique au type (ex: {ride_id, new_status},
            // {message_id}, {transaction_id, amount}) — pas de schéma fixe
            // commun aux 4 types, d'où un JSON libre plutôt que des colonnes.
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();

            // Pas de updated_at : une notification n'est jamais modifiée,
            // seulement marquée lue (read_at) — même pattern que messages.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
