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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            // nullOnDelete plutôt que cascade : si un produit est retiré du
            // catalogue plus tard, on garde l'historique de la commande —
            // product_name/unit_price sont déjà des copies figées ci-dessous,
            // product_id ne sert plus qu'à retrouver le produit s'il existe
            // encore.
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Copie figée au moment de la commande — jamais une référence
            // dynamique au prix/nom actuel du produit.
            $table->string('product_name');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('line_total', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
