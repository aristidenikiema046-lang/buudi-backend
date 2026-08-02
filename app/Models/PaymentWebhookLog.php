<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'payload',
        'headers',
        'result',
        'transaction_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
    ];
}
