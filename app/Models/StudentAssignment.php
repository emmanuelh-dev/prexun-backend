<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAssignment extends Model
{
    use SoftDeletes;
    
    protected $table = 'student_assignments';

    protected $fillable = [
        'student_id',
        'period_id',
        'grupo_id',
        'semana_intensiva_id',
        'assigned_at',
        'valid_until',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the student that this assignment belongs to.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the period that this assignment belongs to.
     */
    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Get the group that this assignment belongs to.
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Get the semana intensiva that this assignment belongs to.
     */
    public function semanaIntensiva()
    {
        return $this->belongsTo(SemanaIntensiva::class, 'semana_intensiva_id');
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include current assignments (not expired).
     */
    public function scopeCurrent($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
        });
    }
}
