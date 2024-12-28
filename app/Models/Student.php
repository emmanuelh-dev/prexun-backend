<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'period_id',
        'username',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'campus_id',
        'period_id',
        'carrer_id',
        'facultad_id',
        'prepa_id',
        'municipio_id',
        'tutor_name',
        'tutor_phone',
        'tutor_relationship',
        'average',
        'attempts',
        'score',
        'health_conditions',
        'how_found_out',
        'preferred_communication',
        'status',
        'general_book',
        'module_book',
        'promo_id'
    ];

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function prepa()
    {
        return $this->belongsTo(Prepa::class);
    }

    public function facultad()
    {
        return $this->belongsTo(Facultad::class);
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'carrer_id');
    }

    public function charges()
    {
        return $this->hasMany(Transaction::class, 'student_id');
    }

    public function promo()
    {
        return $this->belongsTo(Promocion::class, 'promo_id');
    }
}
