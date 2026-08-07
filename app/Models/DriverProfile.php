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
        'vehicle_registration_url',
        'insurance_url',
        'license_expires_at',
        'insurance_expires_at',
        'last_latitude',
        'last_longitude',
        'last_location_at',
    ];

    /**
     * Conversion automatique des attributs
     */
    protected $casts = [
        'is_online' => 'boolean', // 👈 Garantit que la valeur sort sous forme de true/false
        'license_expires_at' => 'datetime',
        'insurance_expires_at' => 'datetime',
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
        'last_location_at' => 'datetime',
    ];

    // Relation inverse vers l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}