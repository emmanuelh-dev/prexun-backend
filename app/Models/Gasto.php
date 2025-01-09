<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Campus;
use App\Models\User;

class Gasto extends Model
{
    protected $fillable = [
        'category',
        'concept',
        'amount',
        'date',
        'method',
        'image',
        'campus_id',
        'admin_id',
        'user_id',
        'cash_cut_id',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class);
    }

    public function gastoDetails()
    {
        return $this->hasMany(GastoDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function scopeCampus($query, $campus_id)
    {
        return $query->where('campus_id', $campus_id);
    }

    public function scopeAdmin($query, $admin_id)
    {
        return $query->where('admin_id', $admin_id);
    }

    public function scopeUser($query, $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    public function scopeDate($query, $date)
    {
        return $query->where('date', $date);
    }
}
