<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('passenger_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('driver_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Adresses & Coordonnées
            $table->string('pickup_address');
            $table->double('pickup_latitude');
            $table->double('pickup_longitude');
            
            $table->string('destination_address');
            $table->double('destination_latitude');
            $table->double('destination_longitude');

            // Tarification & Distance
            $table->decimal('price', 10, 2);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->enum('payment_method', ['cash', 'wallet', 'wave', 'orange_money'])->default('cash');

            // Statuts de la course
            $table->enum('status', [
                'pending',      // En attente d'un chauffeur
                'accepted',     // Accepter par le chauffeur
                'arrived',      // Chauffeur arrivé au point de départ
                'in_progress',  // Course démarrée
                'completed',    // Course terminée
                'cancelled'     // Course annulée
            ])->default('pending');

            // Horodatages du cycle de vie
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};