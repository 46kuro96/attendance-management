<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'approver_id',
        'before_payload',
        'after_payload',
        'reason',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'before_payload' => 'array',
        'after_payload'  => 'array',
        'reviewed_at'    => 'datetime',
    ];

    // ユーザー（申請者)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 承認者（管理者)
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // 対象の勤怠
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // 承認ログ
    public function logs()
    {
        return $this->hasMany(ApprovalLog::class, 'request_id');
    }
}
