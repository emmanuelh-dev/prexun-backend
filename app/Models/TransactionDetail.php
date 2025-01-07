<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    protected $fillable = [
        'transaction_id',
        'denomination_id',
        'quantity'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function denomination()
    {
        return $this->belongsTo(Denomination::class);
    }
}