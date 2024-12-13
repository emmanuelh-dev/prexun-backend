<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function carreras()
    {
        return $this->belongsToMany(Carrera::class, 'carrer_modulo', 'modulo_id', 'carrer_id');
    }
}