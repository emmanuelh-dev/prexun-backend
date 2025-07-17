<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        
        if ($studentId) {
            $student = Student::findOrFail($studentId);
            $notes = $student->notes()->latest()->get();
        } else {
            $notes = Note::latest()->get();
        }
        
        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'text' => 'required|string|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $note = Note::create($request->only(['student_id', 'text']));
        $note->load('student:id,firstname,lastname');

        return response()->json($note, 201);
    }

    public function show(Note $note): JsonResponse
    {
        $note->load('student:id,firstname,lastname');
        return response()->json($note);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $note->update($request->only(['text']));
        $note->load('student:id,firstname,lastname');

        return response()->json($note);
    }

    public function destroy(Note $note): JsonResponse
    {
        $note->delete();
        
        return response()->json([
            'message' => 'Nota eliminada correctamente'
        ]);
    }

    public function getStudentNotes(Student $student): JsonResponse
    {
        $notes = $student->notes()->latest()->get();
        
        return response()->json([
            'student' => $student->only(['id', 'firstname', 'lastname', 'email']),
            'notes' => $notes
        ]);
    }
}
