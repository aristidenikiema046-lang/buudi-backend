<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'is_online', // 👈 Ajouté ici
        'rejection_reason',
        'profile_image_url',
        'cni_url',
        'license_url',
        'selfie_url',
        'criminal_record_url',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_plate',
        'vehicle_seats',
        'vehicle_image_url',
    ];

    /**
     * Conversion automatique des attributs
     */
    protected $casts = [
        'is_online' => 'boolean', // 👈 Garantit que la valeur sort sous forme de true/false
    ];

    // Relation inverse vers l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}