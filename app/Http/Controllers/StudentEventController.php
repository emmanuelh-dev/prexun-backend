<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentEvent;
use Illuminate\Http\Request;

class StudentEventController extends Controller
{
    /**
     * Get all events for a specific student.
     */
    public function getStudentEvents($studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $events = $student->events()
            ->with('user:id,name,email')
            ->paginate(20);

        return response()->json([
            'student' => $student->only(['id', 'firstname', 'lastname', 'email']),
            'events' => $events
        ]);
    }

    /**
     * Get recent events for a student.
     */
    public function getRecentStudentEvents($studentId, $limit = 10)
    {
        $student = Student::findOrFail($studentId);
        
        $events = $student->recentEvents($limit)
            ->with('user:id,name,email')
            ->get();

        return response()->json([
            'student' => $student->only(['id', 'firstname', 'lastname', 'email']),
            'recent_events' => $events
        ]);
    }

    /**
     * Get events by type.
     */
    public function getEventsByType($type)
    {
        $events = StudentEvent::ofType($type)
            ->with(['student:id,firstname,lastname,email', 'user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($events);
    }

    /**
     * Get all movement events (grupo, semana intensiva, period changes).
     */
    public function getMovementEvents()
    {
        $movementTypes = [
            StudentEvent::EVENT_MOVED,
            StudentEvent::EVENT_GRUPO_CHANGED,
            StudentEvent::EVENT_SEMANA_INTENSIVA_CHANGED,
            StudentEvent::EVENT_PERIOD_CHANGED
        ];

        $events = StudentEvent::whereIn('event_type', $movementTypes)
            ->with(['student:id,firstname,lastname,email', 'user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($events);
    }

    /**
     * Example of manually logging a custom event.
     */
    public function logCustomEvent(Request $request, $studentId)
    {
        $request->validate([
            'event_type' => 'required|string',
            'description' => 'required|string',
            'metadata' => 'nullable|array'
        ]);

        $student = Student::findOrFail($studentId);

        $event = $student->logEvent(
            $request->event_type,
            $request->description,
            null,
            null,
            null,
            $request->metadata
        );

        return response()->json([
            'message' => 'Evento registrado exitosamente',
            'event' => $event->load('user:id,name,email')
        ]);
    }

    /**
     * Get audit trail for a student (all changes with before/after data).
     */
    public function getAuditTrail($studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $events = $student->events()
            ->whereNotNull('data_before')
            ->orWhereNotNull('data_after')
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        $auditTrail = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'description' => $event->description,
                'changes_description' => $event->changes_description,
                'changed_fields' => $event->changed_fields,
                'data_before' => $event->data_before,
                'data_after' => $event->data_after,
                'user' => $event->user,
                'created_at' => $event->created_at,
                'ip_address' => $event->ip_address
            ];
        });

        return response()->json([
            'student' => $student->only(['id', 'firstname', 'lastname', 'email']),
            'audit_trail' => $auditTrail
        ]);
    }
}
