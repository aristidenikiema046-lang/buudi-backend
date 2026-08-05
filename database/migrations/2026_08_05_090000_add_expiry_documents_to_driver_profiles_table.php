<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Corrige un bug réel : l'écran d'inscription livreur (buudi_partner_app)
     * envoie déjà une carte grise et une assurance, mais faute de colonnes
     * dédiées ici, il les glissait dans vehicle_image_url/criminal_record_url
     * — deux champs sans rapport. Ce correctif backend est couplé à la
     * correction du contournement côté Flutter (voir delivery_register_screen.dart).
     *
     * license_expires_at / insurance_expires_at : la carte grise n'expire pas
     * (pas de colonne date pour elle), le permis et l'assurance oui — seuls
     * ces deux-là sont trackés pour ce MVP. Remplies manuellement par un
     * admin lors de la vérification du dossier (aucun écran Flutter ne
     * capture ces dates aujourd'hui), pas de valeur par défaut.
     */
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->string('vehicle_registration_url')->nullable()->after('vehicle_image_url');
            $table->string('insurance_url')->nullable()->after('vehicle_registration_url');

            $table->timestamp('license_expires_at')->nullable()->after('insurance_url');
            $table->timestamp('insurance_expires_at')->nullable()->after('license_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'vehicle_registration_url',
                'insurance_url',
                'license_expires_at',
                'insurance_expires_at',
            ]);
        });
    }
};
