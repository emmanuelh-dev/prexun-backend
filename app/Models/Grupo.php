<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = [
        'name',
        'type',
        'plantel_id',
        'period_id',
        'capacity',
        'frequency',
        'start_time',
        'end_time',
        'start_date',
        'end_date'
    ];

    public function period()
    {
        return $this->belongsTo(Period::class);
    }
    public function student()
    {
        return $this->hasMany(Student::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
