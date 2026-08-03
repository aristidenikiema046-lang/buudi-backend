<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Nouvelles valeurs de payment_method (remplace cash/wallet/wave/orange_money
        //    par wallet/mobile_money/carte/especes, alignées sur le flux client).
        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_payment_method_check');
        DB::statement("ALTER TABLE rides ALTER COLUMN payment_method DROP DEFAULT");
        DB::statement(
            "ALTER TABLE rides ADD CONSTRAINT rides_payment_method_check " .
            "CHECK (payment_method IN ('wallet', 'mobile_money', 'carte', 'especes'))"
        );
        DB::statement("ALTER TABLE rides ALTER COLUMN payment_method SET DEFAULT 'especes'");

        // 2. Colonnes manquantes pour la demande de course côté client.
        Schema::table('rides', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('destination_longitude');
            $table->integer('duration_min')->nullable()->after('distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'duration_min']);
        });

        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_payment_method_check');
        DB::statement("ALTER TABLE rides ALTER COLUMN payment_method DROP DEFAULT");
        DB::statement(
            "ALTER TABLE rides ADD CONSTRAINT rides_payment_method_check " .
            "CHECK (payment_method IN ('cash', 'wallet', 'wave', 'orange_money'))"
        );
        DB::statement("ALTER TABLE rides ALTER COLUMN payment_method SET DEFAULT 'cash'");
    }
};
