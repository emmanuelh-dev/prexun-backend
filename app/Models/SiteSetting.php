<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'value',
        'type',
        'description',
        'options',
        'group',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'value' => 'string' // Mantenemos como string para flexibilidad
    ];

    /**
     * Obtener el valor parseado según el tipo
     */
    public function getParsedValueAttribute()
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($this->value) ? (float) $this->value : $this->value;
            case 'json':
                return json_decode($this->value, true);
            case 'array':
                return is_string($this->value) ? explode(',', $this->value) : $this->value;
            default:
                return $this->value;
        }
    }

    /**
     * Obtener una configuración por su key
     */
    public static function getValue($key, $default = null)
    {
        $setting = static::where('key', $key)->where('is_active', true)->first();
        return $setting ? $setting->parsed_value : $default;
    }

    /**
     * Establecer una configuración
     */
    public static function setValue($key, $value, $type = 'text')
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'type' => $type,
                'is_active' => true
            ]
        );
    }

    /**
     * Obtener configuraciones por grupo
     */
    public static function getByGroup($group)
    {
        return static::where('group', $group)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Obtener todas las configuraciones como array key => value
     */
    public static function getAllSettings()
    {
        return static::where('is_active', true)
            ->get()
            ->pluck('parsed_value', 'key')
            ->toArray();
    }
}
