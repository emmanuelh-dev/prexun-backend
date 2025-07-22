<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        
        if ($studentId) {
            $student = Student::findOrFail($studentId);
            $notes = $student->notes()->with('user:id,name')->latest()->get();
        } else {
            $notes = Note::with('user:id,name')->latest()->get();
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

        $note = Note::create([
            'student_id' => $request->student_id,
            'user_id' => Auth::id(),
            'text' => $request->text,
        ]);
        $note->load(['student:id,firstname,lastname', 'user:id,name']);

        return response()->json($note, 201);
    }

    public function show(Note $note): JsonResponse
    {
        $note->load(['student:id,firstname,lastname', 'user:id,name']);
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
        $note->load(['student:id,firstname,lastname', 'user:id,name']);

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
        $notes = $student->notes()->with('user:id,name')->latest()->get();
        
        return response()->json([
            'student' => $student->only(['id', 'firstname', 'lastname', 'email']),
            'notes' => $notes
        ]);
    }
}
