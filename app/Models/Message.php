<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    // Pas de colonne updated_at en base — seul created_at est géré
    // automatiquement par Eloquent à la création.
    const UPDATED_AT = null;

    protected $fillable = [
        'ride_id',
        'sender_id',
        'sender_role',
        'content',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
