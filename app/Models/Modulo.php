<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';
    public $timestamps = false;

    protected $fillable = ['name'];
}
