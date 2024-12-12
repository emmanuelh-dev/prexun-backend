<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'carreers';
    public $timestamps = false;
    protected $fillable = ['name', 'facultad_id'];

    public function facultad()
    {
        return $this->belongsTo(Facultad::class);
    }
}
