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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('users')->onDelete('cascade');

            // Identifiant public opaque utilisé dans l'URL partageable —
            // volontairement distinct de "id" pour ne jamais exposer une
            // référence interne directement.
            $table->string('token')->unique();

            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();

            // Rempli une fois le vrai paiement branché (webhook mobile money
            // ou transfert wallet-à-wallet) — pas encore utilisé aujourd'hui.
            $table->uuid('transaction_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
