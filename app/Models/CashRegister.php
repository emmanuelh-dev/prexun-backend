<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegister extends Model
{
    protected $fillable = [
        'initial_amount',
        'initial_amount_cash',
        'final_amount',
        'final_amount_cash',
        'next_day',
        'next_day_cash',
        'opened_at',
        'closed_at',
        'campus_id',
        'notes',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'initial_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

    public function capus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'abierta');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'cerrada');
    }

    // Métodos
    public function getCurrentBalance()
    {
        $incomingTotal = $this->transactions()
            ->where('type', 'entrada')
            ->sum('total_amount');

        $outgoingTotal = $this->transactions()
            ->where('type', 'salida')
            ->sum('total_amount');

        return $this->initial_amount + $incomingTotal - $outgoingTotal;
    }

    public function getDifference()
    {
        if (!$this->final_amount) {
            return null;
        }

        return $this->final_amount - $this->getCurrentBalance();
    }

    public function close($finalAmount, $notes = null)
    {
        $this->update([
            'final_amount' => $finalAmount,
            'closed_at' => now(),
            'status' => 'cerrada',
            'notes' => $notes
        ]);
    }

    public function getTransactionsSummary()
    {
        return [
            'total_income' => $this->transactions()->where('type', 'entrada')->sum('total_amount'),
            'total_outgoing' => $this->transactions()->where('type', 'salida')->sum('total_amount'),
            'total_transactions' => $this->transactions()->count()
        ];
    }

    public function getDenominationsSummary()
    {
        return $this->transactions()
            ->with('details.denomination')
            ->get()
            ->pluck('details')
            ->flatten()
            ->groupBy('denomination_id')
            ->map(function ($details) {
                $denomination = $details->first()->denomination;
                return [
                    'value' => $denomination->value,
                    'type' => $denomination->type,
                    'total_quantity' => $details->sum('quantity'),
                    'total_amount' => $denomination->value * $details->sum('quantity')
                ];
            });
    }
}