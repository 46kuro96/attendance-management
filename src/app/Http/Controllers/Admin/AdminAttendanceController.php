<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    // 日次：全ユーザーの勤怠一覧
    public function index(Request $request)
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        // 指定日の出勤情報をユーザー順で
        $attendances = Attendance::with(['user:id,name,email','breaks' => function($q) {
                $q->orderBy('start');
            }])
            ->onDate($date)
            ->orderBy('user_id')
            ->get();

        $day  = Carbon::parse($date);
        $prev = $day->copy()->subDay()->toDateString();
        $next = $day->copy()->addDay()->toDateString();

        return view('admin.attendances.index', compact('attendances','day','prev','next'));
    }

    // 勤怠1件の詳細
    public function show(Attendance $attendance)
    {
        $attendance->load(['user:id,name,email','breaks']);
        $breaks = $attendance->breaks->sortBy('start')->values();

        return view('admin.attendances.show', [
            'attendance' => $attendance,
            'b1' => $breaks[0] ?? null,
            'b2' => $breaks[1] ?? null,
        ]);
    }

    // 勤怠1件の更新（管理者）入力は H:i（時刻）で受け、work_date と結合して datetime 保存
    public function update(AdminAttendanceRequest $request, Attendance $attendance)
    {
        DB::transaction(function () use ($request, $attendance) {
            $workDate = $attendance->work_date->toDateString();

            $clockIn  = $request->filled('clock_in')  ? Carbon::parse("$workDate {$request->clock_in}")  : null;
            $clockOut = $request->filled('clock_out') ? Carbon::parse("$workDate {$request->clock_out}") : null;

            // 本体更新
            $attendance->update([
                'clock_in'  => $clockIn,
                'clock_out' => $clockOut,
                'note'      => $request->note,
                'status'    => ($clockIn && $clockOut) ? 'completed' : 'working',
            ]);

            // 休憩を入れ替え
            $attendance->breaks()->delete();

            $rows = [];
            foreach ($request->input('breaks', []) as $b) {
                $start = !empty($b['start']) ? Carbon::parse("$workDate {$b['start']}") : null;
                $end   = !empty($b['end'])   ? Carbon::parse("$workDate {$b['end']}")   : null;

                if ($start || $end) {
                    $rows[] = [
                        'start'   => $start,
                        'end'     => $end,
                        'minutes' => ($start && $end) ? $end->diffInMinutes($start) : 0,
                    ];
                }
            }
            if (!empty($rows)) {
                $attendance->breaks()->createMany($rows);
            }

            // 実働分を再計算して保存
            $attendance->refresh();
            $attendance->recalcWorkMinutes();
        });

        return redirect()
            ->route('admin.attendances.show', $attendance->id)
            ->with('success', '勤怠を更新しました。');
    }

    // 指定ユーザーの勤怠一覧（月次）
    public function monthly(Request $request, User $user)
    {
        // month 取得＆バリデーション（YYYY-MM 以外は今月にフォールバック）
        $month = (string) $request->query('month', '');
        if ($month === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        // 対象月の開始・終了日
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $ym    = $month;

        // 対象月の勤怠を一括取得（休憩合計 minutes を withSum で同時取得）
        // ページネーションはせず、月内全日を埋めるため Collection にします
        $records = Attendance::with([
                'breaks' => fn($q) => $q->orderBy('start'),
            ])
            ->withSum('breaks as break_minutes', 'minutes') // WorkBreak.minutes を想定
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

            // 必ず 'Y-m-d' 文字列をキーにする
        $byDate = $records->keyBy(function ($a) {
            return $a->work_date instanceof \Carbon\Carbon
                ? $a->work_date->toDateString()
                : Carbon::parse($a->work_date)->toDateString();
        });

        // 月内全日分の $days を構築（未打刻日も空で出す）
        $days = [];
        $period = CarbonPeriod::create($start, $end);

        $fmtHi = function ($dt) {
        if (!$dt) return '';

        if ($dt instanceof \Carbon\Carbon) {
            // 24時間形式で出力
            return $dt->locale('en')->translatedFormat('H:i');
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', (string)$dt)
                ->locale('en')
                ->translatedFormat('H:i');
        } catch (\Exception $e) {
            return '';
        }
    };
        $fmtMin = fn(int $m) => sprintf('%d:%02d', intdiv($m,60), $m%60);

        foreach ($period as $date) {
            $key = $date->toDateString();
            $att = $byDate->get($key);

            // 休憩合計（分）
            $breakMin = (int) ($att->break_minutes ?? 0);

            // 実働（分）: clock_in/out があれば差分から休憩を引く
            if ($att && $att->clock_in && $att->clock_out) {
                $in  = Carbon::parse($att->clock_in);
                $out = Carbon::parse($att->clock_out);
                $gross = max(0, $out->diffInMinutes($in));
                $net   = max(0, $gross - $breakMin);
            } else {
                $net = 0;
            }

            $days[] = [
                'date' => $date, // Carbon
                'attendance' => $att, // null の可能性あり
                'clock_in' => $att ? $fmtHi($att->clock_in)  : '',
                'clock_out' => $att ? $fmtHi($att->clock_out) : '',
                'break_total' => $breakMin ? $fmtMin($breakMin) : '',
                'work_total' => $net ? $fmtMin($net) : '',
            ];
        }

        // 前月/翌月（リンク用）
        $prevYm = $start->copy()->subMonth()->format('Y-m');
        $nextYm = $start->copy()->addMonth()->format('Y-m');

        // ビューへ
        return view('admin.attendances.staff_show', compact(
            'user', 'month', 'ym', 'prevYm', 'nextYm', 'days'
        ));
    }

    // 指定ユーザーの勤怠一覧CSV出力
    public function exportCsv(Request $request, User $user)
    {
        $month = $request->query('month') ?: now()->format('Y-m');
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end   = (clone $start)->endOfMonth();

        $attendances = \App\Models\Attendance::withSum('breaks as break_minutes', 'minutes')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        // CSV出力
        $filename = "{$user->name}_{$month}_勤怠一覧.csv";

        $response = new StreamedResponse(function () use ($attendances) {
            $stream = fopen('php://output', 'w');
            // ヘッダー行
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩', '実働']);

            foreach ($attendances as $a) {
                $in   = $a->clock_in  ? \Carbon\Carbon::parse($a->clock_in)->format('H:i') : '';
                $out  = $a->clock_out ? \Carbon\Carbon::parse($a->clock_out)->format('H:i') : '';
                $break = $a->break_minutes ? sprintf('%d分', $a->break_minutes) : '';
                $work  = $a->work_minutes ? sprintf('%d分', $a->work_minutes) : '';
                fputcsv($stream, [$a->work_date, $in, $out, $break, $work]);
            }
            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=Shift-JIS');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    public function approve(int $attendanceId)
    {
        $att = Attendance::findOrFail($attendanceId);

        // ステータスを「completed（退勤済）」に変更して実働再計算
        $att->status = 'completed';
        $att->recalcWorkMinutes();
        $att->save();

        return redirect()
            ->route('admin.attendances.show', $att->id);
    }
}