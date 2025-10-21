<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RequestList;
use App\Http\Requests\AttendanceUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // 出勤操作ページ
    public function create()
    {
        $today = now()->toDateString();
        $attendance = Attendance::firstOrCreate(
            ['user_id' => Auth::id(), 'work_date' => $today],
            ['status' => 'working']
        )->load('breaks');

        return view('attendance.create', compact('attendance'));
    }

    // 自分の勤怠一覧
    public function index(Request $req)
    {
        $ym = $req->input('ym', now()->format('Y-m'));
        $start = Carbon::parse($ym . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = Attendance::with('breaks')
            ->ofUser(Auth::id())
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $a = $records->get($key);

            $breakMin = $a?->breaks?->sum('minutes');

            $days[] = [
                'date' => $cursor->copy(),
                'attendance' => $a,
                'clock_in' => $a?->clock_in?->format('H:i'),
                'clock_out' => $a?->clock_out?->format('H:i'),
                'break_total' => isset($breakMin) ? sprintf('%d:%02d', intdiv($breakMin,60), $breakMin%60) : null,
                'work_total' => $a?->work_hours_text,
            ];
            $cursor->addDay();
        }

        $prevYm = $start->copy()->subMonth()->format('Y-m');
        $nextYm = $start->copy()->addMonth()->format('Y-m');

        return view('attendance.index', [
            'days' => $days,
            'ym' => $start->format('Y/m'),
            'prevYm' => $prevYm,
            'nextYm' => $nextYm,
        ]);
    }

    // 勤怠詳細（本人以外は403）
    public function show($id)
    {
        $attendance = Attendance::with(['breaks','user'])->findOrFail($id);
        abort_unless($attendance->user_id === Auth::id(), 403);

        $breaks = $attendance->breaks->values();
        $b1 = $breaks[0] ?? null;
        $b2 = $breaks[1] ?? null;
        $breakTotal = (int) $attendance->breaks->sum('minutes');

        $hasPending = RequestList::where('attendance_id', $attendance->id)
        ->where('user_id', Auth::id())
        ->where('status', 'pending')
        ->exists();

        return view('attendance.show', compact('attendance', 'breaks', 'b1', 'b2', 'breakTotal', 'hasPending'));
    }

    // 修正申請
    public function requestUpdate(AttendanceUpdateRequest $request, $id)
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);
        abort_unless($attendance->user_id === Auth::id(), 403);

        $exists = RequestList::where('attendance_id', $attendance->id)
        ->where('user_id', Auth::id())
        ->where('status', 'pending')
        ->exists();

        if ($exists) {
            return back()->with('error', '既に承認待ちの申請があります。処理完了までお待ちください。');
    }

        // 現在値（before）— H:i に正規化
        $before = [
            'clock_in' => optional($attendance->clock_in)->format('H:i'),
            'clock_out' => optional($attendance->clock_out)->format('H:i'),
            'note' => $attendance->note,
            'breaks' => $attendance->breaks->map(fn($b) => [
                'start' => optional($b->start)->format('H:i'),
                'end' => optional($b->end)->format('H:i'),
            ])->values()->all(),
        ];

        // 申請内容（after）— フォーム値をそのまま保存
        $v = $request->validated();
        $after = [
            'clock_in' => $v['clock_in']  ?? null,
            'clock_out' => $v['clock_out'] ?? null,
            // 申請理由は note に反映したい内容として保持
            'note' => $v['reason'] ?? null,
            'breaks' => collect($request->input('breaks', []))
                            ->map(fn($b) => [
                                'start' => $b['start'] ?? null,
                                'end'   => $b['end']   ?? null,
                            ])->values()->all(),
        ];

        DB::transaction(function () use ($attendance, $before, $after, $v) {
            RequestList::create([
                'user_id' => Auth::id(),
                'attendance_id' => $attendance->id,
                'before_payload' => $before,
                'after_payload' => $after,
                'reason' => $v['reason'] ?? '',
                'status' => 'pending',
            ]);
        });

        return redirect()
            ->route('attendance.show', $attendance->id)
            ->with('success', '修正申請を送信しました。');
    }

    // 出勤（now固定）
    public function clockIn()
    {
        $today = now()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => Auth::id(), 'work_date' => $today],
            ['status' => 'working']
        );

        if ($attendance->clock_in) {
            return back()->with('error', 'すでに出勤済みです。');
        }

        $attendance->update([
            'clock_in' => now(),
            'status' => 'working',
        ]);

        return back()->with('status', '出勤を記録しました。');
    }

    // 退勤（now固定）
    public function clockOut()
    {
        $today = now()->toDateString();

        $result = DB::transaction(function () use ($today) {
            $att = Attendance::where('user_id', Auth::id())
                ->whereDate('work_date', $today)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$att->clock_in) {
                return ['error' => '先に出勤してください。'];
            }
            if ($att->clock_out) {
                return ['error' => '既に退勤済みです。'];
            }

            if ($open = $att->breaks()->whereNull('end')->latest('start')->first()) {
                $end = now();
                $minutes = $open->start->diffInMinutes($end);
                $open->update(['end' => $end, 'minutes' => $minutes]);
            }

            $att->update([
                'clock_out' => now(),
                'status' => 'completed',
            ]);

            $att->refresh();
            $att->recalcWorkMinutes();

            return ['status' => '退勤を記録しました。'];
        });

        return isset($result['error'])
            ? back()->with('error', $result['error'])
            : back()->with('status', $result['status']);
    }

    // 休憩開始
    public function breakStart()
    {
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('work_date', $today)
            ->firstOrFail();

        if (!$attendance->clock_in) {
            return back()->with('error', '先に出勤してください。');
        }
        if ($attendance->clock_out) {
            return back()->with('error', '退勤後は休憩できません。');
        }
        if ($attendance->breaks()->whereNull('end')->exists()) {
            return back()->with('error', '未終了の休憩があります。');
        }

        $attendance->breaks()->create(['start' => now()]);

        return back()->with('status', '休憩を開始しました。');
    }

    // 休憩終了
    public function breakEnd()
    {
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('work_date', $today)
            ->firstOrFail();

        $open = $attendance->breaks()->whereNull('end')->latest('start')->first();
        if (!$open) {
            return back()->with('error', '開始中の休憩がありません。');
        }

        $now = now();
        $end = $attendance->clock_out ? $attendance->clock_out->copy()->min($now) : $now;

        $minutes = $open->start->diffInMinutes($end);
        $open->update(['end' => $end, 'minutes' => $minutes]);

        if ($attendance->clock_in && $attendance->clock_out) {
            $attendance->refresh();
            $attendance->recalcWorkMinutes();
        }

        return back()->with('status', '休憩を終了しました。');
    }
}