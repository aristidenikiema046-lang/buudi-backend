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
        Schema::create('driver_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->integer('amount_paid')->default(2000); // Montant de l'activation (2 000 FCFA)
            $table->timestamp('starts_at');
            $table->timestamp('expires_at'); // Expiration 24h après activation
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_subscriptions');
    }
};