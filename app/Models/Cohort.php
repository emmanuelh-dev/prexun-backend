<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'period_id',
        'group_id',
    ];

    /**
     * Get the period that owns the cohort.
     */
    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Get the group that owns the cohort.
     */
    public function group()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * The students that belong to the cohort.
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_cohort')
            ->withPivot('created_at', 'updated_at');
    }
}
