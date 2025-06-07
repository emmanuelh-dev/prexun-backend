<?php

namespace App\Traits;

use App\Models\StudentEvent;

trait LogsStudentEvents
{
    /**
     * Boot the trait.
     */
    public static function bootLogsStudentEvents()
    {
        // Log when a student is created
        static::created(function ($student) {
            StudentEvent::createEvent(
                $student->id,
                StudentEvent::EVENT_CREATED,
                null,
                $student->toArray(),
                'Estudiante creado',
                null,
                ['action' => 'create']
            );
        });

        // Log when a student is updated
        static::updated(function ($student) {
            $original = $student->getOriginal();
            $changes = $student->getChanges();
            
            // Remove timestamps from changes tracking
            unset($changes['updated_at']);
            
            if (!empty($changes)) {
                $changedFields = array_keys($changes);
                $description = 'Estudiante actualizado: ' . implode(', ', $changedFields);
                
                // Check for specific important changes
                if (in_array('grupo_id', $changedFields)) {
                    $description = 'Estudiante movido a nuevo grupo';
                    $eventType = StudentEvent::EVENT_GRUPO_CHANGED;
                } elseif (in_array('semana_intensiva_id', $changedFields)) {
                    $description = 'Estudiante asignado a semana intensiva';
                    $eventType = StudentEvent::EVENT_SEMANA_INTENSIVA_CHANGED;
                } elseif (in_array('period_id', $changedFields)) {
                    $description = 'Estudiante movido a nuevo período';
                    $eventType = StudentEvent::EVENT_PERIOD_CHANGED;
                } else {
                    $eventType = StudentEvent::EVENT_UPDATED;
                }

                StudentEvent::createEvent(
                    $student->id,
                    $eventType,
                    $original,
                    $student->toArray(),
                    $description,
                    $changedFields,
                    ['action' => 'update', 'changes' => $changes]
                );
            }
        });

        // Log when a student is soft deleted
        static::deleted(function ($student) {
            StudentEvent::createEvent(
                $student->id,
                StudentEvent::EVENT_DELETED,
                $student->toArray(),
                null,
                'Estudiante eliminado',
                null,
                ['action' => 'delete']
            );
        });

        // Log when a student is restored from soft delete
        static::restored(function ($student) {
            StudentEvent::createEvent(
                $student->id,
                StudentEvent::EVENT_RESTORED,
                null,
                $student->toArray(),
                'Estudiante restaurado',
                null,
                ['action' => 'restore']
            );
        });
    }

    /**
     * Log a custom event for this student.
     */
    public function logEvent($eventType, $description, $dataBefore = null, $dataAfter = null, $changedFields = null, $metadata = null)
    {
        return StudentEvent::createEvent(
            $this->id,
            $eventType,
            $dataBefore,
            $dataAfter,
            $description,
            $changedFields,
            $metadata
        );
    }

    /**
     * Log a movement event (grupo, semana intensiva, or period change).
     */
    public function logMovementEvent($fromValue, $toValue, $field, $description = null)
    {
        $eventType = match($field) {
            'grupo_id' => StudentEvent::EVENT_GRUPO_CHANGED,
            'semana_intensiva_id' => StudentEvent::EVENT_SEMANA_INTENSIVA_CHANGED,
            'period_id' => StudentEvent::EVENT_PERIOD_CHANGED,
            default => StudentEvent::EVENT_MOVED
        };

        $description = $description ?: "Estudiante movido: {$field} cambió de {$fromValue} a {$toValue}";

        return $this->logEvent(
            $eventType,
            $description,
            [$field => $fromValue],
            [$field => $toValue],
            [$field],
            [
                'field' => $field,
                'from' => $fromValue,
                'to' => $toValue,
                'action' => 'movement'
            ]
        );
    }
}
