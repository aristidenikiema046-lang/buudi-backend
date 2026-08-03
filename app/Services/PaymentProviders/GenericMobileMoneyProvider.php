<?php

namespace App\Services\PaymentProviders;

use Illuminate\Http\Request;

/**
 * Implémentation par défaut, entièrement pilotée par la config
 * (services.payment_providers.{provider}) : nom du header de signature,
 * algorithme, noms des champs du payload. Utilisée pour tous les fournisseurs
 * tant qu'aucun n'a un format vraiment différent (signature RSA, payload
 * imbriqué...) — dans ce cas-là seulement, créer une classe dédiée
 * implémentant PaymentProviderInterface et l'enregistrer dans
 * PaymentProviderFactory.
 */
class GenericMobileMoneyProvider implements PaymentProviderInterface
{
    public function __construct(private array $config)
    {
    }

    public function verifySignature(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        // Pas de secret configuré = fournisseur pas encore branché : on
        // n'exige pas de signature (pratique en développement), mais ça
        // deviendra automatiquement strict dès qu'un secret sera renseigné.
        if (!$secret) {
            return true;
        }

        $headerName = $this->config['signature_header'] ?? 'X-Signature';
        $algo = $this->config['signature_algo'] ?? 'sha256';

        $signature = $request->header($headerName);
        if (!$signature) {
            return false;
        }

        $expected = hash_hmac($algo, $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function extractMerchantReference(Request $request): ?string
    {
        $field = $this->config['merchant_reference_field'] ?? 'reference';

        return $request->input($field);
    }

    public function extractExternalReference(Request $request): ?string
    {
        $field = $this->config['external_reference_field'] ?? 'external_reference';

        return $request->input($field);
    }

    public function extractStatus(Request $request): ?string
    {
        $field = $this->config['status_field'] ?? 'status';
        $raw = $request->input($field);

        if ($raw === null) {
            return null;
        }

        $map = $this->config['status_map'] ?? ['completed' => 'completed', 'failed' => 'failed'];

        return $map[$raw] ?? null;
    }
}
