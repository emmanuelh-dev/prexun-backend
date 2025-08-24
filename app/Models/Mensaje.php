<?php
// esto es para guardar mensajes vinculados a un estudiante 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Mensaje extends Model
{
    protected $table = 'mensajes'; // esto le dice a laravel que este modelo representa la tabla de mensajes
    
    protected $fillable = [
        'mensaje', 
        'student_id', 
        'phone_number', 
        'direction', 
        'message_type', 
        'session_id',
        'user_id'
    ]; // indica que columnas se pueden llenar desde el backend
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Relación con el modelo Student (si existe)
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    
    /**
     * Relación con el modelo User (quien maneja la conversación)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Scope para mensajes enviados
     */
    public function scopeSent($query)
    {
        return $query->where('direction', 'sent');
    }
    
    /**
     * Scope para mensajes recibidos
     */
    public function scopeReceived($query)
    {
        return $query->where('direction', 'received');
    }
    
    /**
     * Scope para filtrar por número de teléfono
     */
    public function scopeForPhone($query, $phoneNumber)
    {
        return $query->where('phone_number', $phoneNumber);
    }
    
    /**
     * Scope para filtrar por sesión
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
    
    /**
     * Obtener conversación completa por número de teléfono
     */
    public static function getConversation($phoneNumber, $limit = 50)
    {
        return self::forPhone($phoneNumber)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
    
    /**
     * Crear o obtener session_id para una conversación
     */
    public static function getOrCreateSession($phoneNumber, $userId = null)
    {
        $latestMessage = self::forPhone($phoneNumber)
            ->whereNotNull('session_id')
            ->latest()
            ->first();
            
        if ($latestMessage && $latestMessage->session_id) {
            return $latestMessage->session_id;
        }
        
        // Crear nuevo session_id único
        return 'whatsapp_' . $phoneNumber . '_' . time();
    }
}
