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
        Schema::table('transactions', function (Blueprint $table) {
            // ID de transaction côté opérateur mobile money, distinct de
            // "reference_id" (qui sert à lier une transaction à une course).
            // Unique : PostgreSQL autorise plusieurs NULL sur un index unique,
            // donc ça ne gêne pas les transactions internes (dépôt, retrait,
            // gains course...) qui n'ont pas de référence externe.
            $table->string('external_reference')->nullable()->unique()->after('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['external_reference']);
            $table->dropColumn('external_reference');
        });
    }
};
