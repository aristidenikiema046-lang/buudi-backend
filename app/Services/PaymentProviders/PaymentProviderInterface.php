<?php

namespace App\Services\PaymentProviders;

use Illuminate\Http\Request;

/**
 * Contrat commun à tous les fournisseurs de paiement mobile money (opérateur
 * direct ou agrégateur). WebhookController ne dépend que de cette interface,
 * jamais d'un fournisseur précis — brancher un nouveau fournisseur ou changer
 * de stratégie (opérateurs directs vs agrégateur) ne demande donc aucun
 * changement dans WebhookController.
 */
interface PaymentProviderInterface
{
    /**
     * Vérifie la signature de la requête webhook. Doit retourner true si
     * aucun secret n'est encore configuré (permet de tester en local avant
     * d'avoir les vraies clés — voir GenericMobileMoneyProvider).
     */
    public function verifySignature(Request $request): bool;

    /**
     * Extrait NOTRE référence (l'UUID de la Transaction transmis au fournisseur
     * au moment du transfert), pour retrouver la Transaction correspondante.
     */
    public function extractMerchantReference(Request $request): ?string;

    /**
     * Extrait l'ID de transaction propre au fournisseur — sert uniquement à
     * l'idempotence stricte et à l'audit, pas à la corrélation.
     */
    public function extractExternalReference(Request $request): ?string;

    /**
     * Extrait le statut, déjà traduit vers 'completed'|'failed'. Retourne
     * null si la valeur reçue est inconnue/non mappée.
     */
    public function extractStatus(Request $request): ?string;
}
