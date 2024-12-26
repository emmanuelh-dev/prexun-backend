<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCuts extends Model
{
    protected $fillable = [
        'user_id',
        'initial_amount',
        'final_amount',
        'real_amount',
        'date',
        'campus_id'
    ];
}
