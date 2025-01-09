<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoDetail extends Model
{
    protected $fillable = [
        'gasto_id',
        'denomination_id',
        'quantity'
    ];

    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }
    public function denomination()
    {
        return $this->belongsTo(Denomination::class);
    }
}
