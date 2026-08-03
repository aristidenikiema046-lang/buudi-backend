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
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            // Corps brut + en-têtes de la requête reçue, tels quels — utile
            // pour rejouer/déboguer un vrai problème de paiement en prod sans
            // avoir à redemander les logs à l'opérateur.
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            // received (toujours écrit en premier) -> invalid_signature,
            // invalid_payload, reference_not_found, already_processed,
            // duplicate_external_reference, ou processed.
            $table->string('result')->default('received');
            $table->uuid('transaction_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
