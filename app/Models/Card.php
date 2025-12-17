<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cards';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'name',
        'clabe',
        'sat',
        'campus_id',
        'is_hidden',
    ];

    /**
     * Get the campus associated with the card.
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}