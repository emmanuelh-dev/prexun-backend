<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'regular_cost',
        'cost',
        'limit_date',
        'groups',
        'pagos',
        'active'
    ];

    protected $casts = [
        'limit_date' => 'datetime',
        'groups' => 'array',
        'pagos' => 'array',
        'active' => 'boolean'
    ];
}