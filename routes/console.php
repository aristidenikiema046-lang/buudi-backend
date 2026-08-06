<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('drivers:check-document-expiry')->daily();

// Contrairement aux documents (échéance à la journée), la règle métier
// exige une déconnexion "immédiate" à l'expiration du pass 24h — fréquence
// la plus courte que le scheduler standard supporte nativement.
Schedule::command('drivers:enforce-subscription-expiry')->everyFiveMinutes();
