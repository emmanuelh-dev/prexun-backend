<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CarreraModulo extends Pivot
{
    protected $table = 'carrer_modulo';

    public $incrementing = true;

    protected $fillable = ['carrer_id', 'modulo_id'];
}
