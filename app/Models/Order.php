<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'merchant_profile_id',
        'status',
        'subtotal',
        'delivery_fee',
        'total',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'payment_request_id',
        'ride_id',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
