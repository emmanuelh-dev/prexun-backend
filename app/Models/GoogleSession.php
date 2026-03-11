<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSession extends Model
{
    protected $fillable = [
        'campus_id',
        'email',
        'access_token',
        'refresh_token',
        'expires_in',
        'token_data',
        'is_active',
    ];

    protected $casts = [
        'expires_in' => 'datetime',
        'token_data' => 'array',
        'is_active' => 'boolean',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}