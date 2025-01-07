<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'code', 
        'description', 
        'address', 
        'is_active'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'campus_user');
    }

    public function latestCashRegister()
    {
        return $this->hasOne(CashRegister::class)
                    ->where('status', 'abierta')
                    ->latest();
    }

    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }

    public function caja()
    {
        return $this->hasOne(CashRegister::class)
                    ->where('status', 'abierta')
                    ->latest();
    }
}
