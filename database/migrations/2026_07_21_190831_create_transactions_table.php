<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['credit', 'debit']); // 'credit' = Gain / 'debit' = Retrait ou Commission
            $table->string('category'); // ex: 'ride_earning', 'tip', 'commission', 'subscription'
            $table->string('description')->nullable();
            $table->foreignUuid('reference_id')->nullable(); // ID de la course associée
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};