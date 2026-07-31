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
        'business_name',
        'business_type',
        'business_address',
        'business_phone',
        'rejection_reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
