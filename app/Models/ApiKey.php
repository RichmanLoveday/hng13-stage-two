<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'permissions',
        'expires_at',
        'revoked'
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'revoked' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}