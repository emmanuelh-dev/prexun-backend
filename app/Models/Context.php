<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Context extends Model
{
    protected $fillable = [
        'name',
        'instructions',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Obtener contexto por nombre
     */
    public static function getByName($name)
    {
        return self::where('name', $name)->where('is_active', true)->first();
    }

    /**
     * Crear contexto predeterminado para WhatsApp
     */
    public static function createWhatsAppDefault()
    {
        return self::create([
            'name' => 'whatsapp_default',
            'instructions' => 'Eres un asistente virtual útil y amigable para WhatsApp. Responde de manera concisa y clara.',
            'is_active' => true
        ]);
    }

    /**
     * Activar contexto
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Desactivar contexto
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
