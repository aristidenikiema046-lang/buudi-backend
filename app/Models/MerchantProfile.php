<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MerchantProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'status',
        'commission_rate',
        'business_name',
        'business_type',
        'business_address',
        'business_phone',
        'logo_url',
        'rejection_reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
