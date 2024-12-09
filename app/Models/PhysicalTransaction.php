<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'transaction_type',
        'amount',
        'payment_method',
        'denominations',
        'receipt_number',
        'notes'
    ];

    protected $casts = [
        'denominations' => 'array',
        'amount' => 'decimal:2'
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}