<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'direction',
        'amount',
        'reference',
        'status',
        'related_wallet_id',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];


    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsToMany(User::class);
    }
}