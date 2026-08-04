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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('merchant_profile_id')->constrained('merchant_profiles')->cascadeOnDelete();

            // Volontairement réduit à pending/confirmed/cancelled : une fois
            // "confirmed", la progression de la livraison est suivie via le
            // Ride lié (ride_id), pas dupliquée ici.
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');

            // subtotal = somme des order_items (marchandises), payé via
            // payment_request_id. delivery_fee alimente rides.price une fois
            // la course créée. total = subtotal + delivery_fee, affiché au
            // client mais jamais débité en une seule fois (deux flux d'argent
            // séparés : marchandises vs livraison, comme le reste de l'app).
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('total', 10, 2);

            // Adresse de livraison — nécessaire pour créer le Ride à la
            // confirmation, pas encore capturée nulle part côté Order.
            $table->string('delivery_address');
            $table->double('delivery_latitude');
            $table->double('delivery_longitude');

            $table->foreignUuid('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
            $table->foreignUuid('ride_id')->nullable()->constrained('rides')->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
