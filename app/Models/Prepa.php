<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prepa extends Model
{
    protected $table = 'prepas';
    protected $fillable = ['name'];

    public $timestamps = false;
}
