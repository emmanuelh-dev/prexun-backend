<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Period extends Model
{

    protected $fillable = [
        'name',
        'start_date',
        'price',
        'end_date'
    ];
}
