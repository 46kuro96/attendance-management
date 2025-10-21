<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        // 勤務外・未打刻
        $date = Carbon::today();

        return [
            'user_id'      => User::factory(),
            'work_date'    => $date->toDateString(),
            'clock_in'     => null,
            'clock_out'    => null,
            'work_minutes' => 0,
            'note'         => null,
            'status'       => 'working',
        ];
    }

    //きょうの日付に固定
    public function today(): self
    {
        return $this->state(fn () => [
            'work_date' => Carbon::today()->toDateString(),
        ]);
    }

    // 出勤中（退勤前）
    public function working(): self
    {
        $in = Carbon::today()->copy()->setTime(9, 0);

        return $this->state(fn () => [
            'clock_in'     => $in,
            'clock_out'    => null,
            'work_minutes' => 0,
            'status'       => 'working',
        ]);
    }

    // 退勤済（実働を自動計算）
    public function finished(int $hours = 8, int $breakMinutes = 60): self
    {
        $in  = Carbon::today()->copy()->setTime(9, 0);
        $out = (clone $in)->addHours($hours)->addMinutes($breakMinutes);

        $workTotal = $in->diffInMinutes($out); // 実時間
        $workMins  = max($workTotal - $breakMinutes, 0); // 休憩控除

        return $this->state(fn () => [
            'clock_in'     => $in,
            'clock_out'    => $out,
            'work_minutes' => $workMins,
            'status'       => 'completed',
        ]);
    }

    public function absent(): self
    {
        return $this->state(fn () => ['status' => 'absent']);
    }

    public function leave(): self
    {
        return $this->state(fn () => ['status' => 'leave']);
    }
}