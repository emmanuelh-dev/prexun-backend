<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanaIntensiva extends Model
{
    
    protected $table = 'semanas_intensivas';

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
        'end_date',
        'moodle_id'
    ];

    public function period()
    {
        return $this->belongsTo(Period::class);
    }
}
