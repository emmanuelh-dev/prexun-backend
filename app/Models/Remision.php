<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remision extends Model
{
    protected $fillable = ['campus_id', 'user_id', 'transaction_id', 'payment_date', 'amount', 'title', 'description'];
}
