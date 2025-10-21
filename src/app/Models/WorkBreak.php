<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkBreak extends Model
{
    use HasFactory;

    protected $table = 'breaks';

    protected $fillable = ['attendance_id', 'start', 'end', 'minutes'];

    protected $casts = [
        'start' => 'datetime',
        'end'   => 'datetime',
    ];

    // 親勤怠
    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    // minutes を自動計算（任意） start/end の両方があるときだけ分差を入れる
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->start && $model->end) {
                $model->minutes = $model->end->diffInMinutes($model->start);
            }
        });
    }
}