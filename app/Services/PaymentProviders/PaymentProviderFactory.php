<?php

namespace App\Services\PaymentProviders;

class PaymentProviderFactory
{
    /**
     * Construit le service adapté pour un fournisseur donné ("wave",
     * "orange_money", "mtn_momo", "moov_money", "aggregator_dexchange").
     * Retourne null si le fournisseur n'est pas connu de la config.
     */
    public static function make(string $provider): ?PaymentProviderInterface
    {
        $config = config("services.payment_providers.$provider");

        if (!$config) {
            return null;
        }

        // TODO: si un fournisseur a un format vraiment différent une fois sa
        // vraie doc API connue, ajouter un cas ici pointant vers une classe
        // dédiée au lieu de GenericMobileMoneyProvider — ex:
        // 'aggregator_dexchange' => new DexchangeProvider($config),
        return new GenericMobileMoneyProvider($config);
    }

    /**
     * Liste des identifiants de fournisseurs valides, dérivée de la config
     * plutôt qu'un tableau en dur dans le contrôleur.
     */
    public static function knownProviders(): array
    {
        return array_keys(config('services.payment_providers', []));
    }
}
