<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCampus extends Model
{
    protected $table = 'user_campuses';

    protected $fillable = ['user_id', 'campus_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
