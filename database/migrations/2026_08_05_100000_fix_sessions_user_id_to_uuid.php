<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * sessions.user_id est resté en bigint (stub Laravel par défaut, jamais
     * adapté) alors que users.id est un UUID depuis
     * alter_users_table_id_to_uuid — invisible jusqu'ici car le JWT mobile
     * n'utilise jamais la table sessions ; seul le panel Filament (guard
     * web, session réelle) l'exploite pour la première fois.
     *
     * TRUNCATE avant l'ALTER : uniquement des sessions web de test créées
     * pendant le développement du panel, aucune donnée métier — et de
     * toute façon aucune conversion bigint -> uuid sensée n'existe pour
     * d'éventuelles lignes existantes.
     */
    public function up(): void
    {
        DB::table('sessions')->truncate();

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            // Même convention que le reste du projet (wallets, driver_debts,
            // merchant_profiles...) : foreignUuid, sans ->constrained() —
            // le stub Laravel d'origine ne mettait pas non plus de vraie
            // contrainte FK ici (une session existe même si le user est
            // supprimé entre-temps, ou pour un visiteur non connecté).
            $table->foreignUuid('user_id')->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sessions')->truncate();

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index()->after('id');
        });
    }
};
