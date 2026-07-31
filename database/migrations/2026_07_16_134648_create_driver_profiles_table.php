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
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // URLs des documents (Firebase)
            $table->string('profile_image_url');
            $table->string('cni_url');
            $table->string('license_url');
            $table->string('selfie_url');
            $table->string('criminal_record_url')->nullable();
            
            // Informations véhicule
            $table->string('vehicle_type');
            $table->string('vehicle_brand');
            $table->string('vehicle_model');
            $table->integer('vehicle_year');
            $table->string('vehicle_color');
            $table->string('vehicle_plate')->unique(); // <-- La colonne requise par ta validation
            $table->integer('vehicle_seats');
            $table->string('vehicle_image_url');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};