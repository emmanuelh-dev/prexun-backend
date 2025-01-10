<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Denomination extends Model
{
    protected $fillable = ['value', 'type'];

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
