<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Une contrainte CHECK classique ne peut pas comparer l'ancienne et la
     * nouvelle valeur d'une colonne — il faut un trigger. Règle appliquée :
     * une fois qu'une transaction est "completed" ou "failed", son statut
     * est gelé pour toujours. Seule la transition pending -> completed/failed
     * reste possible. Ça bloque au niveau base de données toute tentative de
     * ré-écriture, même par du code qui contournerait la logique applicative.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION enforce_transaction_status_transition()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.status IN ('completed', 'failed') AND NEW.status IS DISTINCT FROM OLD.status THEN
                    RAISE EXCEPTION 'Transition de statut interdite sur transactions.id=% : % -> % (un statut final est definitif)', OLD.id, OLD.status, NEW.status;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_enforce_transaction_status_transition
            BEFORE UPDATE ON transactions
            FOR EACH ROW
            EXECUTE FUNCTION enforce_transaction_status_transition();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_enforce_transaction_status_transition ON transactions');
        DB::statement('DROP FUNCTION IF EXISTS enforce_transaction_status_transition()');
    }
};
