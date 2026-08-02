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
        Schema::table('payment_requests', function (Blueprint $table) {
            // Qui a payé — nullable puisque la plupart des demandes ne le
            // sont pas encore. Pas de contrainte onDelete cascade : on ne
            // veut pas perdre l'historique d'une demande payée si le compte
            // payeur est un jour supprimé.
            $table->foreignUuid('payer_id')->nullable()->after('merchant_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropForeign(['payer_id']);
            $table->dropColumn('payer_id');
        });
    }
};
