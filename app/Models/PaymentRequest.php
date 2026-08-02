<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'merchant_id',
        'token',
        'amount',
        'description',
        'status',
        'expires_at',
        'paid_at',
        'transaction_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Fait passer la demande à "expired" en base si elle est encore "pending"
     * mais que l'échéance est dépassée. À appeler avant de renvoyer le statut
     * à qui que ce soit (pas de tâche planifiée pour l'instant — l'expiration
     * est constatée au moment de la consultation).
     */
    public function refreshExpiryStatus(): self
    {
        if ($this->status === 'pending' && now()->greaterThan($this->expires_at)) {
            $this->update(['status' => 'expired']);
        }

        return $this;
    }
}
