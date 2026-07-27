<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverDebt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'driver_id',
        'ride_id',
        'commission_amount',
        'due_date',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at'  => 'datetime',
        'is_paid'  => 'boolean',
    ];

    // Relation avec le chauffeur (User)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}