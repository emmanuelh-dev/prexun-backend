# Notes API Documentation

This document describes the Notes API endpoints that were created for managing student notes.

## Overview

The Notes system allows you to create, read, update, and delete notes associated with students. Each note contains:
- `id`: Unique identifier
- `student_id`: Foreign key to the students table
- `user_id`: Foreign key to the users table (automatically set from authenticated user)
- `text`: The note content (text field)
- `created_at`: Timestamp when the note was created
- `updated_at`: Timestamp when the note was last updated

## Database Structure

### Migration
- **File**: `2025_07_17_060450_create_notes_table.php`
- **Table**: `notes`
- **Foreign Key**: `student_id` references `students.id` with cascade delete

### Model
- **File**: `app/Models/Note.php`
- **Relationships**: 
  - `belongsTo(Student::class)` - Each note belongs to a student
- **Fillable**: `['student_id', 'user_id', 'text']`
- **Relationships**: 
  - `belongsTo(Student::class)` - Each note belongs to a student
  - `belongsTo(User::class)` - Each note belongs to a user (creator)

### Controller
- **File**: `app/Http/Controllers/NoteController.php`
- **Type**: API Resource Controller

## API Endpoints

All endpoints require authentication (`auth:sanctum` middleware).

### 1. List Notes
```
GET /api/notes
```
**Query Parameters:**
- `student_id` (optional): Filter notes by student ID

**Response:**
```json
[
  {
    "id": 1,
    "student_id": 123,
    "text": "Student shows excellent progress in mathematics",
    "created_at": "2025-07-17T10:30:00.000000Z",
    "updated_at": "2025-07-17T10:30:00.000000Z",
    "student": {
      "id": 123,
      "firstname": "Juan",
      "lastname": "Pérez"
    }
  }
]
```

### 2. Create Note
```
POST /api/notes
```
**Request Body:**
```json
{
  "student_id": 123,
  "text": "Student shows excellent progress in mathematics"
}
```

**Validation Rules:**
- `student_id`: required, must exist in students table
- `text`: required, string, max 65535 characters
- `user_id`: automatically set from authenticated user session

### 3. Show Note
```
GET /api/notes/{note}
```
**Response:**
```json
{
  "id": 1,
  "student_id": 123,
  "user_id": 1,
  "text": "Student shows excellent progress",
  "created_at": "2025-07-17T10:30:00.000000Z",
  "updated_at": "2025-07-17T10:30:00.000000Z",
  "student": {
    "id": 123,
    "firstname": "Juan",
    "lastname": "Pérez"
  },
  "user": {
    "id": 1,
    "name": "Admin User"
  }
}
```

### 4. Update Note
```
PUT /api/notes/{note}
```
**Request Body:**
```json
{
  "text": "Updated note content"
}
```

**Validation Rules:**
- `text`: required, string, max 65535 characters

### 5. Delete Note
```
DELETE /api/notes/{note}
```
**Response:**
```json
{
  "message": "Nota eliminada correctamente"
}
```

### 6. Get Student Notes
```
GET /api/students/{student}/notes
```
**Response:**
```json
{
  "student": {
    "id": 123,
    "firstname": "Juan",
    "lastname": "Pérez",
    "email": "juan.perez@example.com"
  },
  "notes": [
    {
      "id": 1,
      "student_id": 123,
      "text": "First note",
      "created_at": "2025-07-17T10:30:00.000000Z",
      "updated_at": "2025-07-17T10:30:00.000000Z"
    }
  ]
}
```

## Usage Examples

### Create a note for a student
```bash
curl -X POST http://localhost:8000/api/notes \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "student_id": 123,
    "text": "Student participated actively in class discussion"
  }'
```

### Get all notes for a specific student
```bash
curl -X GET "http://localhost:8000/api/notes?student_id=123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Update a note
```bash
curl -X PUT http://localhost:8000/api/notes/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "text": "Updated: Student shows significant improvement"
  }'
```

## Error Responses

### Validation Error (422)
```json
{
  "message": "Error de validación",
  "errors": {
    "student_id": ["The selected student id is invalid."]
  }
}
```

### Not Found (404)
```json
{
  "message": "No query results for model [App\\Models\\Note] 1"
}
```

## Model Relationships

### In Student Model
```php
public function notes()
{
    return $this->hasMany(Note::class);
}
```

### In Note Model
```php
public function student(): BelongsTo
{
    return $this->belongsTo(Student::class);
}
```

## Security Features

- All endpoints require authentication via Sanctum
- Foreign key constraints ensure data integrity
- Cascade delete: when a student is deleted, all their notes are automatically deleted
- Input validation prevents malicious data
- Mass assignment protection via `$fillable` array

## Performance Considerations

- Notes are ordered by `latest()` (newest first) for better UX
- Eager loading of student relationships to prevent N+1 queries
- Text field supports up to 65535 characters for long notes
- Proper indexing on `student_id` foreign key for fast queries