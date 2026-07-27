<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'driver_id',
        'amount_paid',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relation avec le chauffeur (User)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}