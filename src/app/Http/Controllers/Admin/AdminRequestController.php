<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestList;
use App\Models\ApprovalLog;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRequestController extends Controller
{
    public function index(Request $req)
    {
        $tab = $req->get('tab','pending');
        $q = RequestList::with(['user:id,name','attendance'])->orderByDesc('created_at');
        $tab === 'approved' ? $q->where('status','approved') : $q->where('status','pending');
        $requests = $q->paginate(20)->withQueryString();

        return view('admin.request_lists.index', compact('requests','tab'));
    }

    public function show($id)
    {
        $requestList = RequestList::with([
            'user:id,name,email',
            'attendance.user:id,name',
            'attendance.breaks',
            'logs.actor:id,name',
        ])->findOrFail($id);

        $att = $requestList->attendance;

        $decode = fn($v) => is_array($v) ? $v : (json_decode($v ?? '', true) ?: []);
        $before = $decode($requestList->before_payload);
        $after  = $decode($requestList->after_payload);

        $pick = fn($k, $fb=null) => $after[$k] ?? ($before[$k] ?? $fb);

        $aBr = collect($after['breaks'] ?? []);
        $bBr = collect($before['breaks'] ?? []);
        $br  = function($i,$which) use($aBr,$bBr,$att){
            $a = data_get($aBr->get($i),$which);
            $b = data_get($bBr->get($i),$which);
            if ($a || $b) return $a ?: $b;
            $row = optional($att)->breaks[$i] ?? null;
            return $row ? optional($row->{$which})->format('H:i') : null;
        };

        $vm = [
            'name' => optional($requestList->user)->name ?? optional($att?->user)->name,
            'y' => optional($att?->work_date)->format('Y年'),
            'md' => optional($att?->work_date)->format('n月j日'),
            'clock_in' => $pick('clock_in',  optional($att?->clock_in)->format('H:i')),
            'clock_out' => $pick('clock_out', optional($att?->clock_out)->format('H:i')),
            'b1s' => $br(0,'start'),
            'b1e' => $br(0,'end'),
            'b2s' => $br(1,'start'),
            'b2e' => $br(1,'end'),
            'note' => $pick('note', $requestList->reason ?? ($att->note ?? '')),
            'created_at' => optional($requestList->created_at)->format('Y/m/d H:i'),
            'status' => $requestList->status,
        ];

        return view('admin.request_lists.show', compact('requestList','vm'));
    }

    public function approve(Request $req, $id)
    {
        try {
            DB::transaction(function () use ($req, $id) {
                // モデル取得（悲観ロック）
                $requestList = RequestList::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($requestList->status !== 'pending') {
                    throw new \RuntimeException('already-processed');
                }

                $att = $requestList->attendance;
                if (!$att) {
                    throw new \RuntimeException('attendance-not-found');
                }

                $after = (array) $requestList->after_payload;
                $workDate = $att->work_date->toDateString();

                $clockIn = !empty($after['clock_in'])  ? \Carbon\Carbon::parse("$workDate {$after['clock_in']}")  : $att->clock_in;
                $clockOut = !empty($after['clock_out']) ? \Carbon\Carbon::parse("$workDate {$after['clock_out']}") : $att->clock_out;

                // 勤怠本体更新
                $att->update([
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'note' => $after['note'] ?? $att->note,
                ]);

               // 休憩入れ替え
                $att->breaks()->delete();
                if (!empty($after['breaks']) && is_array($after['breaks'])) {
                    $rows = [];
                    foreach ($after['breaks'] as $b) {
                        $start = !empty($b['start']) ? \Carbon\Carbon::parse("$workDate {$b['start']}") : null;
                        $end = !empty($b['end'])   ? \Carbon\Carbon::parse("$workDate {$b['end']}")   : null;
                        $minutes = ($start && $end) ? max(0, $end->diffInMinutes($start)) : 0;
                        $rows[] = ['start'=>$start, 'end'=>$end, 'minutes'=>$minutes];
                    }
                    if ($rows) $att->breaks()->createMany($rows);
                }

                // 実働再計算
                $breakMins = (int) $att->breaks()->sum('minutes');
                $net = ($clockIn && $clockOut)
                    ? max(0, $clockOut->diffInMinutes($clockIn) - $breakMins)
                    : 0;

                $att->update([
                    'work_minutes' => $net,
                    'status' => ($clockIn && $clockOut) ? 'completed' : 'working',
                ]);

                // リクエスト更新 + ログ
                $requestList->update([
                    'status' => 'approved',
                    'approver_id' => $req->user()->id,
                    'reviewed_at' => now(),
                ]);

                ApprovalLog::create([
                    'request_id' => $requestList->id,
                    'action' => 'approved',
                    'actor_id' => $req->user()->id,
                    'comment' => $req->input('comment'),
                ]);
        });


        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'already-processed') {
                return back()->with('error', '既に処理済みです。');
            }
            if ($e->getMessage() === 'attendance-not-found') {
                return back()->with('error', '対象の勤怠が見つかりません。');
            }
            return back()->with('error', '処理に失敗しました。');
        }

        return redirect()
        ->route('admin.request_lists.show', ['id' => $id])
        ->with('approved', true);
    }
}