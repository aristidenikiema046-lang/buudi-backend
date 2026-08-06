<?php

namespace App\Console\Commands;

use App\Jobs\CreateNotificationJob;
use App\Models\DriverProfile;
use App\Models\DriverSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnforceDriverSubscriptionExpiry extends Command
{
    protected $signature = 'drivers:enforce-subscription-expiry';

    protected $description = "Remet hors ligne les chauffeurs dont l'abonnement journalier (pass 2 000 FCFA/24h) a expiré, et maintient le champ status des abonnements en conséquence.";

    /**
     * Deux passes indépendantes, chacune naturellement idempotente sans
     * bookkeeping supplémentaire : un abonnement déjà 'expired' ne
     * remmatche plus le filtre status='active' de la passe 1 ; un profil
     * déjà is_online=false ne remmatche plus le filtre de la passe 2.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        // 1. Maintenance du champ status, indépendamment de is_online — un
        // abonnement expiré reste 'expired' même si le chauffeur ne s'est
        // jamais reconnecté depuis.
        $expiredCount = DriverSubscription::where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired']);

        // 2. Déconnexion active : tout profil en ligne sans abonnement
        // actuellement valide (expiré à l'instant, ou jamais eu). Reprend
        // exactement la condition de DriverEligibilityService::checkCanGoOnline().
        $driversWithActiveSubscription = DriverSubscription::where('status', 'active')
            ->where('expires_at', '>', $now)
            ->pluck('driver_id');

        $profilesToDisconnect = DriverProfile::where('is_online', true)
            ->whereNotIn('user_id', $driversWithActiveSubscription)
            ->get(['id', 'user_id']);

        foreach ($profilesToDisconnect as $profile) {
            $profile->update(['is_online' => false]);

            CreateNotificationJob::dispatch(
                $profile->user_id,
                'subscription_expired',
                'Pass journalier expiré',
                'Votre pass journalier de 2 000 FCFA a expiré : vous avez été remis hors ligne. Rechargez votre pass pour reprendre les courses.',
                []
            );
        }

        $this->info("{$expiredCount} abonnement(s) marqué(s) 'expired', {$profilesToDisconnect->count()} chauffeur(s) déconnecté(s).");

        return self::SUCCESS;
    }
}
