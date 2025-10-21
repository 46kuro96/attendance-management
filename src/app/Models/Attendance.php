<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    // 保存を許可するカラム
    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in',
        'clock_out',
        'work_minutes',
        'note',
        'status',
    ];

    // 型キャストはここで
    protected $casts = [
        'work_date' => 'date',
        'clock_in'  => 'datetime',
        'clock_out' => 'datetime',
        'work_minutes' => 'integer',
    ];

    // リレーション
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(WorkBreak::class, 'attendance_id');
    }

    public function requests()
    {
        return $this->hasMany(RequestList::class, 'attendance_id');
    }

    // 実働分を再計算して保存
    public function recalcWorkMinutes(): void
    {
        if ($this->clock_in && $this->clock_out) {
            // 休憩合計（分）
            $breakMinutes = (int) $this->breaks()->sum('minutes');

            // 勤務合計（分）
            $workTotal = $this->clock_in->diffInMinutes($this->clock_out);

            $this->work_minutes = max($workTotal - $breakMinutes, 0);

            $this->saveQuietly();
        }
    }

    // スコープ
    public function scopeOfUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeOnDate($q, $date)
    {
        return $q->whereDate('work_date', $date);
    }

    // 月指定で取り出せる
    public function scopeOfMonth($q, $yearMonth)
    {
        $date = Carbon::parse($yearMonth.'-01');
        return $q->whereBetween('work_date', [$date->startOfMonth(), $date->endOfMonth()]);
    }

    // 時間をH：MMで出せる
    public function getWorkHoursTextAttribute(): string
    {
        $m = $this->work_minutes ?? 0;
        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }
}