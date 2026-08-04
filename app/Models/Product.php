<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'merchant_profile_id',
        'name',
        'description',
        'price',
        'category',
        'image_url',
        'is_available',
        'stock_quantity',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }
}
