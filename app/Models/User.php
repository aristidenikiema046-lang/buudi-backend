<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

// Importation des nouveaux modèles
use App\Models\DriverSubscription;
use App\Models\DriverDebt;
use App\Models\WalletTransaction;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    // Indique à Laravel d'utiliser des chaînes de caractères (UUIDs) pour la clé primaire
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Les attributs qui peuvent être assignés en masse.
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'city',
        'birth_date',
        'gender',
        'password',
        'role',
        'fcm_token'
    ];

    /**
     * Les attributs masqués dans les réponses JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Génération automatique de l'UUID à la création d'un utilisateur
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (empty($user->id)) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relation avec le profil Chauffeur 
     */
    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    /**
     * Relation avec le profil Commerçant
     */
    public function merchantProfile()
    {
        return $this->hasOne(MerchantProfile::class, 'user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    /**
     * Relations Financières (Abonnement, Dettes, Transactions)
     */
    public function subscriptions()
    {
        return $this->hasMany(DriverSubscription::class, 'driver_id');
    }

    public function debts()
    {
        return $this->hasMany(DriverDebt::class, 'driver_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'driver_id');
    }

    /**
     * Transtypage des attributs.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Méthodes requises par le package JWT-Auth
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role ?? 'client',
        ];
    }
}