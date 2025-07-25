<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsStudentEvents;

class Student extends Model
{
    use SoftDeletes;
    protected static function boot()
    {
        parent::boot();

        static::created(function ($student) {
            \App\Models\StudentEvent::createEvent(
                $student->id,
                \App\Models\StudentEvent::EVENT_CREATED,
                null,
                $student->toArray(),
                'Estudiante creado',
                null
            );
        });

        static::updating(function ($student) {
            $original = $student->getOriginal();
            $changes = array_keys($student->getDirty());
            \App\Models\StudentEvent::createEvent(
                $student->id,
                \App\Models\StudentEvent::EVENT_UPDATED,
                $original,
                $student->getDirty(),
                'Estudiante actualizado',
                $changes
            );
        });

        static::deleted(function ($student) {
            \App\Models\StudentEvent::createEvent(
                $student->id,
                \App\Models\StudentEvent::EVENT_DELETED,
                $student->toArray(),
                null,
                'Estudiante eliminado',
                null
            );
        });

        static::restored(function ($student) {
            \App\Models\StudentEvent::createEvent(
                $student->id,
                \App\Models\StudentEvent::EVENT_RESTORED,
                null,
                $student->toArray(),
                'Estudiante restaurado',
                null
            );
        });
    }
    protected $table = 'students';

    protected $fillable = [
        'period_id',
        'username',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'campus_id',
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
        'promo_id',
        'grupo_id',
        'semana_intensiva_id',
        'moodle_id',
        'matricula',
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

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
    public function semana_intensiva()
    {
        return $this->belongsTo(SemanaIntensiva::class, 'semana_intensiva_id');
    }

    public function assignments()
    {
        return $this->hasMany(StudentAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->hasMany(StudentAssignment::class)->active()->current();
    }

    public function events()
    {
        return $this->hasMany(StudentEvent::class)->orderBy('created_at', 'desc');
    }

    public function recentEvents($limit = 10)
    {
        return $this->hasMany(StudentEvent::class)->orderBy('created_at', 'desc')->limit($limit);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
