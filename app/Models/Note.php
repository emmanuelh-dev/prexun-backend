<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = [
        'student_id',
        'text',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
