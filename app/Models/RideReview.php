<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideReview extends Model
{
    use HasFactory, HasUuids;

    // Pas de colonne updated_at en base — un avis n'est jamais modifié une
    // fois posté, même convention que Notification.
    const UPDATED_AT = null;

    protected $fillable = [
        'ride_id',
        'reviewed_user_id',
        'reviewer_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function reviewedUser()
    {
        return $this->belongsTo(User::class, 'reviewed_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Recalcule et persiste rating_average/rating_count sur le driver_profile
     * du chauffeur noté — appelé après chaque nouvel avis. Un simple
     * AVG/COUNT plutôt qu'une moyenne glissante : plus simple, toujours
     * exact, et ne tourne que sur ce chemin d'écriture rare (jamais sur la
     * lecture de profil, qui est le chemin chaud).
     *
     * À appeler à l'intérieur d'une transaction (le lockForUpdate() n'a
     * d'effet que dans ce contexte) : protège contre deux avis simultanés
     * sur des courses différentes du même chauffeur qui recalculeraient en
     * parallèle sur des snapshots obsolètes.
     */
    public static function recalculateRatingFor(string $driverId): void
    {
        $driverProfile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

        if (!$driverProfile) {
            return;
        }

        $stats = self::where('reviewed_user_id', $driverId)
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        $driverProfile->update([
            'rating_average' => $stats->count > 0 ? round((float) $stats->average, 2) : null,
            'rating_count' => $stats->count,
        ]);
    }
}
