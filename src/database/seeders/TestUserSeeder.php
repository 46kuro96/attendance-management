<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\WorkBreak;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            //  テストユーザー（認証済み）
            $user = User::updateOrCreate(
                ['email' => 'user@example.com'],
                [
                    'name' => 'Test User',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'email_verified_at' => now(), // 認証済みに
                ]
            );

            //  タイムゾーン固定（週末判定のズレ防止）
            $tz = config('app.timezone', 'Asia/Tokyo');
            $today = Carbon::today($tz);

            //  直近60日ぶんの既存レコードを掃除（任意で期間調整）
            Attendance::where('user_id', $user->id)
                ->where('work_date', '>=', $today->copy()->subDays(60)->toDateString())
                ->get()
                ->each(function ($a) {
                    $a->breaks()->delete();
                    $a->delete();
                });

            //  平日のみ20件を厳密に作る
            $made = 0;
            $cursor = 1;
            while ($made < 20) {
                $date = $today->copy()->subDays($cursor++); // 1日前、2日前…
                if ($date->isWeekend()) {
                    continue; // 土日スキップ
                }

                $isWorking = random_int(1, 100) <= 75; // 75%で出勤

                if (!$isWorking) {
                    Attendance::updateOrCreate(
                        ['user_id' => $user->id, 'work_date' => $date->toDateString()],
                        [
                            'clock_in' => null,
                            'clock_out' => null,
                            'work_minutes' => 0,
                            'note' => null,
                            'status' => random_int(0, 1) ? 'absent' : 'leave',
                        ]
                    );
                    $made++;
                    continue;
                }

                // 出勤・退勤を作成
                $clockIn  = (clone $date)->setTime(random_int(8, 10), [0, 15, 30, 45][random_int(0, 3)]);
                $clockOut = (clone $clockIn)
                    ->addHours(random_int(7, 10))
                    ->addMinutes([0, 15, 30, 45, 60][random_int(0, 4)]);

                // 勤怠レコード（先に作成）
                $attendance = Attendance::updateOrCreate(
                    ['user_id' => $user->id, 'work_date' => $date->toDateString()],
                    [
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'work_minutes' => 0, // 後で再計算
                        'note' => null,
                        'status' => 'working', // 仮
                    ]
                );

                // 一度でも再シードした時に古い休憩が残らないようリセット
                $attendance->breaks()->delete();

                // 0〜2件の休憩
                $breakCount = random_int(0, 2);
                for ($b = 0; $b < $breakCount; $b++) {
                    $start = (clone $clockIn)
                        ->addHours(random_int(2, 6))
                        ->setMinutes([0, 15, 30, 45][random_int(0, 3)]);

                    $duration = [15, 20, 30, 45, 60][random_int(0, 4)];
                    $end = (clone $start)->addMinutes($duration);

                    // 退勤を超えないよう
                    if ($end->greaterThan($clockOut)) {
                        $end   = (clone $clockOut)->subMinutes(5);
                        $start = (clone $end)->subMinutes($duration);
                    }

                    WorkBreak::updateOrCreate(
                        [
                            'attendance_id' => $attendance->id,
                            'start'         => $start,
                        ],
                        [
                            'end'           => $end,
                            // minutes は WorkBreak::booted() で自動計算
                        ]
                    );
                }

                // 実働分を再計算して保存
                $attendance->recalcWorkMinutes();
                $attendance->update(['status' => 'completed']);

                $made++; // 平日1件カウント
            }
        });
    }
}