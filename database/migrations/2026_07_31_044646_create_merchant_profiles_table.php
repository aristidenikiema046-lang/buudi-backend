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
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, approved, rejected

            // Informations commerce (à compléter quand les contrôleurs seront construits)
            $table->string('business_name');
            $table->string('business_type')->nullable(); // ex: restaurant, supermarché, boutique
            $table->string('business_address')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
