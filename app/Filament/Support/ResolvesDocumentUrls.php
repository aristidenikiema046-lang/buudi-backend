<?php

namespace App\Filament\Support;

trait ResolvesDocumentUrls
{
    /**
     * Les URLs de documents stockées en base ont été générées au moment de
     * l'inscription mobile via asset(Storage::url(...)), qui absolutise
     * l'URL avec l'hôte de LA REQUÊTE QUI A SERVI L'INSCRIPTION à ce
     * moment-là (APP_URL n'étant pas fixé). Un chauffeur inscrit depuis son
     * téléphone via l'IP réseau locale (ex: 10.79.185.64:8000) a donc cette
     * IP figée dans ses colonnes *_url pour toujours — inaccessible depuis
     * un navigateur qui consulte le panel sur un autre hôte (127.0.0.1,
     * localhost, le domaine de prod...).
     *
     * On ne fait donc jamais confiance à l'hôte stocké : seul le chemin
     * après /storage/ est conservé, puis réabsolutisé avec l'hôte de la
     * requête EN COURS (celle qui affiche le panel), toujours correcte.
     */
    private static function resolveDocumentUrl(?string $storedUrl): ?string
    {
        if (!$storedUrl) {
            return null;
        }

        $path = parse_url($storedUrl, PHP_URL_PATH);

        if (!$path || !str_starts_with($path, '/storage/')) {
            return $storedUrl;
        }

        return asset(ltrim($path, '/'));
    }
}
