<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nomina extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seccion_id',
        'archivo_original_path',
        'archivo_firmado_path',
        'estado',
        'fecha_firma',
    ];

    protected $casts = [
        'fecha_firma' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(NominaSeccion::class, 'seccion_id');
    }
}
