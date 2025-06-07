<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEvent extends Model
{
    protected $table = 'student_events';

    protected $fillable = [
        'student_id',
        'user_id',
        'event_type',
        'description',
        'data_before',
        'data_after',
        'changed_fields',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'data_before' => 'array',
        'data_after' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
    ];

    // Event types constants
    const EVENT_CREATED = 'created';
    const EVENT_UPDATED = 'updated';
    const EVENT_MOVED = 'moved';
    const EVENT_DELETED = 'deleted';
    const EVENT_RESTORED = 'restored';
    const EVENT_GRUPO_CHANGED = 'grupo_changed';
    const EVENT_SEMANA_INTENSIVA_CHANGED = 'semana_intensiva_changed';
    const EVENT_PERIOD_CHANGED = 'period_changed';

    /**
     * Get the student that this event belongs to.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who triggered this event.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include events of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to only include events for a specific student.
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope a query to only include events by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get a human-readable description of the changes made.
     */
    public function getChangesDescriptionAttribute()
    {
        if (!$this->changed_fields || empty($this->changed_fields)) {
            return $this->description;
        }

        $changes = [];
        foreach ($this->changed_fields as $field) {
            $before = $this->data_before[$field] ?? 'null';
            $after = $this->data_after[$field] ?? 'null';
            $changes[] = "{$field}: {$before} → {$after}";
        }

        return implode(', ', $changes);
    }

    /**
     * Create a new student event record.
     */
    public static function createEvent($studentId, $eventType, $dataBefore = null, $dataAfter = null, $description = null, $changedFields = null, $metadata = null)
    {
        return self::create([
            'student_id' => $studentId,
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'description' => $description,
            'data_before' => $dataBefore,
            'data_after' => $dataAfter,
            'changed_fields' => $changedFields,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
