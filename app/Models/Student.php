<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'username',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'campus_id',
        'period_id'
    ];

    // public function campus()
    // {
    //     return $this->belongsTo(Campus::class);
    // }

    // public function transactions()
    // {
    //     return $this->hasMany(Transaction::class);
    // }
}
