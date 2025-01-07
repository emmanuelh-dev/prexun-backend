<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'student_id',
        'campus_id',
        'transaction_type',
        'cash_registers_id',
        'amount',
        'paid',
        'payment_date',
        'expiration_date',
        'payment_method',
        'denominations',
        'notes',
        'uuid'
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
