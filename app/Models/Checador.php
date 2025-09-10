<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Checador extends Model
{
    use HasFactory;

    protected $table = 'checador';

    protected $fillable = [
        'user_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'status',
        'break_start_at',
        'break_end_at',
        'break_duration',
        'hours_worked',
        'is_complete_day'
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours_worked' => 'decimal:2',
        'is_complete_day' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calculateHoursWorked()
    {
        if (!$this->check_in_at) {
            $this->hours_worked = 0;
            $this->save();
            return;
        }
    

        $checkIn = Carbon::createFromFormat('Y-m-d H:i:s', $this->work_date->format('Y-m-d') . ' ' . $this->check_in_at);
        $checkOut = $this->check_out_at 
            ? Carbon::createFromFormat('Y-m-d H:i:s', $this->work_date->format('Y-m-d') . ' ' . $this->check_out_at)
            : now();
        
        $totalMinutes = $checkIn->diffInMinutes($checkOut);
        if ($this->break_duration) {
            $totalMinutes -= $this->break_duration;
        }
        
        $this->hours_worked = round($totalMinutes / 60, 2);
        $this->is_complete_day = $this->hours_worked >= 6;
        $this->save();
    }

    public function updateHoursWorked()
    {
        $this->calculateHoursWorked();
    }

    public function startBreak()
    {
        $this->break_start_at = now()->format('H:i:s');
        $this->status = 'on_break';
        $this->save();
    }

    public function endBreak()
    {
        if ($this->break_start_at) {
            $this->break_end_at = now()->format('H:i:s');
            
            $breakStart = Carbon::createFromFormat('H:i:s', $this->break_start_at);
            $breakEnd = Carbon::createFromFormat('H:i:s', $this->break_end_at);
            
            $this->break_duration = $breakStart->diffInMinutes($breakEnd);
            $this->status = 'present';
            
            $this->save();
            $this->updateHoursWorked();
        }
    }
}
