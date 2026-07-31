<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Supprimer la clé étrangère qui bloque tout
        DB::statement('ALTER TABLE driver_profiles DROP CONSTRAINT IF EXISTS driver_profiles_user_id_foreign');

        // 2. Retirer l'auto-incrément (séquence) par défaut sur la table users
        DB::statement('ALTER TABLE users ALTER COLUMN id DROP DEFAULT');

        // 3. Convertir la colonne 'id' de 'users' en UUID
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE uuid USING id::text::uuid');

        // 4. Convertir la colonne 'user_id' de 'driver_profiles' en UUID pour qu'elles correspondent
        DB::statement('ALTER TABLE driver_profiles ALTER COLUMN user_id TYPE uuid USING user_id::text::uuid');

        // 5. Recréer proprement la contrainte de clé étrangère entre les deux colonnes UUID
        DB::statement('ALTER TABLE driver_profiles ADD CONSTRAINT driver_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En cas de rollback (facultatif mais propre)
        DB::statement('ALTER TABLE driver_profiles DROP CONSTRAINT IF EXISTS driver_profiles_user_id_foreign');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE bigint USING id::text::bigint');
        DB::statement('ALTER TABLE driver_profiles ALTER COLUMN user_id TYPE bigint USING user_id::text::bigint');
        DB::statement('ALTER TABLE driver_profiles ADD CONSTRAINT driver_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    }
};