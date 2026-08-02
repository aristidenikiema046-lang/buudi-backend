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
        Schema::table('rides', function (Blueprint $table) {
            // Champs utilisés uniquement quand service_type = "Livraison".
            // Le destinataire n'est pas forcément un utilisateur Buudi, d'où
            // de simples nom/téléphone plutôt qu'une relation vers "users".
            $table->string('recipient_name')->nullable()->after('service_type');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->string('package_type')->nullable()->after('recipient_phone');
            $table->decimal('package_weight_kg', 6, 2)->nullable()->after('package_type');
            $table->string('package_code')->nullable()->after('package_weight_kg');
            $table->text('delivery_instructions')->nullable()->after('package_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'recipient_name',
                'recipient_phone',
                'package_type',
                'package_weight_kg',
                'package_code',
                'delivery_instructions',
            ]);
        });
    }
};
