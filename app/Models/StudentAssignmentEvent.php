<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAssignmentEvent extends Model
{
    protected $table = 'student_assignment_events';

    protected $fillable = [
        'student_assignment_id',
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
    const EVENT_DELETED = 'deleted';
    const EVENT_ACTIVATED = 'activated';
    const EVENT_DEACTIVATED = 'deactivated';
    const EVENT_GRUPO_ASSIGNED = 'grupo_assigned';
    const EVENT_GRUPO_REMOVED = 'grupo_removed';
    const EVENT_GRUPO_CHANGED = 'grupo_changed';
    const EVENT_SEMANA_INTENSIVA_ASSIGNED = 'semana_intensiva_assigned';
    const EVENT_SEMANA_INTENSIVA_REMOVED = 'semana_intensiva_removed';
    const EVENT_SEMANA_INTENSIVA_CHANGED = 'semana_intensiva_changed';
    const EVENT_PERIOD_CHANGED = 'period_changed';
    const EVENT_VALIDITY_UPDATED = 'validity_updated';
    const EVENT_BULK_CREATED = 'bulk_created';
    const EVENT_BULK_UPDATED = 'bulk_updated';

    /**
     * Get the student assignment that this event belongs to.
     */
    public function studentAssignment()
    {
        return $this->belongsTo(StudentAssignment::class);
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
     * Scope a query to only include events for a specific student assignment.
     */
    public function scopeForAssignment($query, $assignmentId)
    {
        return $query->where('student_assignment_id', $assignmentId);
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
            
            // Format specific fields for better readability
            switch ($field) {
                case 'grupo_id':
                    $before = $this->formatGrupoId($before);
                    $after = $this->formatGrupoId($after);
                    $changes[] = "Grupo: {$before} → {$after}";
                    break;
                case 'semana_intensiva_id':
                    $before = $this->formatSemanaIntensivaId($before);
                    $after = $this->formatSemanaIntensivaId($after);
                    $changes[] = "Semana Intensiva: {$before} → {$after}";
                    break;
                case 'is_active':
                    $before = $before ? 'Activo' : 'Inactivo';
                    $after = $after ? 'Activo' : 'Inactivo';
                    $changes[] = "Estado: {$before} → {$after}";
                    break;
                case 'valid_until':
                    $before = $before ? date('Y-m-d', strtotime($before)) : 'Sin límite';
                    $after = $after ? date('Y-m-d', strtotime($after)) : 'Sin límite';
                    $changes[] = "Válido hasta: {$before} → {$after}";
                    break;
                default:
                    $changes[] = "{$field}: {$before} → {$after}";
                    break;
            }
        }

        return implode(', ', $changes);
    }

    /**
     * Format grupo ID for display.
     */
    private function formatGrupoId($grupoId)
    {
        if (!$grupoId) return 'Sin grupo';
        
        $grupo = Grupo::find($grupoId);
        return $grupo ? $grupo->name : "Grupo #{$grupoId}";
    }

    /**
     * Format semana intensiva ID for display.
     */
    private function formatSemanaIntensivaId($semanaId)
    {
        if (!$semanaId) return 'Sin semana intensiva';
        
        $semana = SemanaIntensiva::find($semanaId);
        return $semana ? $semana->name : "Semana #{$semanaId}";
    }

    /**
     * Create a new student assignment event record.
     */
    public static function createEvent(
        $assignmentId, 
        $eventType, 
        $dataBefore = null, 
        $dataAfter = null, 
        $description = null, 
        $changedFields = null, 
        $metadata = null
    ) {
        return self::create([
            'student_assignment_id' => $assignmentId,
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

    /**
     * Get event type label for display.
     */
    public function getEventTypeLabelAttribute()
    {
        $labels = [
            self::EVENT_CREATED => 'Asignación creada',
            self::EVENT_UPDATED => 'Asignación actualizada',
            self::EVENT_DELETED => 'Asignación eliminada',
            self::EVENT_ACTIVATED => 'Asignación activada',
            self::EVENT_DEACTIVATED => 'Asignación desactivada',
            self::EVENT_GRUPO_ASSIGNED => 'Grupo asignado',
            self::EVENT_GRUPO_REMOVED => 'Grupo removido',
            self::EVENT_GRUPO_CHANGED => 'Grupo cambiado',
            self::EVENT_SEMANA_INTENSIVA_ASSIGNED => 'Semana intensiva asignada',
            self::EVENT_SEMANA_INTENSIVA_REMOVED => 'Semana intensiva removida',
            self::EVENT_SEMANA_INTENSIVA_CHANGED => 'Semana intensiva cambiada',
            self::EVENT_PERIOD_CHANGED => 'Periodo cambiado',
            self::EVENT_VALIDITY_UPDATED => 'Validez actualizada',
            self::EVENT_BULK_CREATED => 'Creación masiva',
            self::EVENT_BULK_UPDATED => 'Actualización masiva',
        ];

        return $labels[$this->event_type] ?? $this->event_type;
    }
}
