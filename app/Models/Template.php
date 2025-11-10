<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'meta_id',
        'parameters',
        'example_message',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parameters' => 'array'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}