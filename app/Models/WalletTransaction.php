<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WalletTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'driver_id',
        'amount',
        'type',
        'description',
        'status',
    ];

    // Relation avec le chauffeur (User)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}