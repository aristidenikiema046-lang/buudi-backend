<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La colonne phone était NOT NULL malgré la règle de validation
        // "nullable" côté AuthController : toute inscription sans téléphone
        // plantait avec une erreur SQL 500. La contrainte d'unicité (index)
        // reste en place ; PostgreSQL autorise plusieurs NULL sur un index
        // unique, donc ça ne bloque pas deux comptes sans téléphone.
        DB::statement('ALTER TABLE users ALTER COLUMN phone DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN phone SET NOT NULL');
    }
};
