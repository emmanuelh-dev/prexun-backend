<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    protected $table = 'facultades';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function carreras()
    {
        return $this->hasMany(Carrera::class);
    }
}