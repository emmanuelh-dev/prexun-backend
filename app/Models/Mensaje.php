<?php
 // esto es para guardar mensajes vinculados  a un estudiane 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table = 'mensajes'; // esto le dice a laravel que este modelo representa la tabla de mensajs
    protected $fillable = ['nombre', 'mensaje', 'student_id', 'role']; // indica que columans se pueden llenar desde elbackend
}
