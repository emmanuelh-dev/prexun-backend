<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'campus_id',
        'user_id',
        'transaction_type',
        'amount',
        'payment_method',
        'denominations',
        'receipt_number',
        'notes',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
