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
        'business_latitude',
        'business_longitude',
        'business_phone',
        'logo_url',
        'is_supermarket',
        'rejection_reason',
    ];

    protected $casts = [
        'is_supermarket' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
