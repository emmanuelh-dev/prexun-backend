<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'attendances'; 

    protected $fillable = ['grupo_id', 'fecha', 'student_id', 'status'];

    public $timestamps = false;

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }
}
