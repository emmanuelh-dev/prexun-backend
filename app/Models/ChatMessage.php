<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'content',
        'images',
        'metadata',
        'conversation_type',
        'related_id',
        'session_id'
    ];

    protected $casts = [
        'images' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para obtener mensajes de un usuario específico
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para obtener mensajes por rol
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope para obtener mensajes recientes
     */
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Obtener el historial de conversación para un usuario
     */
    public static function getConversationHistory($userId, $limit = 50, $conversationType = null, $sessionId = null)
    {
        $query = self::forUser($userId)->orderBy('created_at', 'asc');
        
        if ($conversationType) {
            $query->where('conversation_type', $conversationType);
        }
        
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }
        
        return $query->limit($limit)->get();
    }

    /**
     * Crear una nueva sesión de conversación
     */
    public static function createSession($userId, $conversationType = 'general', $relatedId = null)
    {
        return uniqid($userId . '_' . $conversationType . '_', true);
    }

    /**
     * Obtener conversaciones agrupadas por sesión
     */
    public static function getConversationsBySession($userId, $limit = 10)
    {
        return self::forUser($userId)
            ->select('session_id', 'conversation_type', 'related_id', 'created_at')
            ->whereNotNull('session_id')
            ->groupBy('session_id', 'conversation_type', 'related_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($conversation) {
                $messageCount = self::where('session_id', $conversation->session_id)->count();
                $lastMessage = self::where('session_id', $conversation->session_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return [
                    'session_id' => $conversation->session_id,
                    'conversation_type' => $conversation->conversation_type,
                    'related_id' => $conversation->related_id,
                    'message_count' => $messageCount,
                    'last_message' => $lastMessage ? $lastMessage->content : null,
                    'last_activity' => $lastMessage ? $lastMessage->created_at : $conversation->created_at,
                ];
            });
    }

    /**
     * Scope para filtrar por tipo de conversación
     */
    public function scopeByConversationType($query, $type)
    {
        return $query->where('conversation_type', $type);
    }

    /**
     * Scope para filtrar por session_id
     */
    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}