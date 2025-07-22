<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Context extends Model
{
    protected $fillable = [
        'whatsapp_id',
        'instructions',
        'user_info',
        'current_state',
        'temp_data',
        'last_interaction',
        'is_active'
    ];

    protected $casts = [
        'user_info' => 'array',
        'temp_data' => 'array',
        'last_interaction' => 'datetime',
        'is_active' => 'boolean'
    ];

    public static function getOrCreateForWhatsApp($whatsappId)
    {
        return self::firstOrCreate(
            ['whatsapp_id' => $whatsappId],
            [
                'current_state' => 'idle',
                'is_active' => true,
                'last_interaction' => now()
            ]
        );
    }

    public function updateInstructions($instructions)
    {
        $this->update([
            'instructions' => $instructions,
            'last_interaction' => now()
        ]);
    }

    public function updateUserInfo($info)
    {
        $currentInfo = $this->user_info ?? [];
        $this->update([
            'user_info' => array_merge($currentInfo, $info),
            'last_interaction' => now()
        ]);
    }

    public function setState($state, $tempData = null)
    {
        $this->update([
            'current_state' => $state,
            'temp_data' => $tempData,
            'last_interaction' => now()
        ]);
    }

    public function reset()
    {
        $this->update([
            'instructions' => null,
            'current_state' => 'idle',
            'temp_data' => null,
            'last_interaction' => now()
        ]);
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByState($query, $state)
    {
        return $query->where('current_state', $state);
    }

    public function scopeRecentlyActive($query, $hours = 24)
    {
        return $query->where('last_interaction', '>=', now()->subHours($hours));
    }
}
