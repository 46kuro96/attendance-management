@extends('layouts.app')

@section('title','勤怠登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendances/create.css') }}">
@endsection

@section('content')
@php
    // 状態判定
    $clockIn   = $attendance->clock_in;
    $clockOut  = $attendance->clock_out;
    $onBreak   = $attendance->breaks->firstWhere('end', null); // 進行中休憩
    if (!$clockIn)        $state = 'before';      // 出勤前
    elseif ($clockOut)    $state = 'after';       // 退勤後
    elseif ($onBreak)     $state = 'breaking';    // 休憩中
    else                  $state = 'working';     // 出勤中
@endphp

<div class="container c-att">
    {{-- バッジ --}}
    <div class="badge">
    @switch($state)
        @case('before')   <span class="tag tag-gray">勤務外</span> @break
        @case('working')  <span class="tag">出勤中</span> @break
        @case('breaking') <span class="tag tag-gray">休憩中</span> @break
        @case('after')    <span class="tag tag-gray">退勤済</span> @break
    @endswitch
</div>

{{-- 日付&時刻表示 --}}
<div class="date">{{ \Carbon\Carbon::parse($attendance->work_date)->isoFormat('YYYY年M月D日(ddd)') }}</div>
<div class="time">{{ now()->format('H:i') }}</div>

{{-- 状態別メインアクション --}}
<div class="actions">
    @if ($state === 'before')
        <form method="post" action="{{ route('attendance.clock_in') }}">
        @csrf
            <button class="btn btn-black" type="submit">出勤</button>
        </form>
    @elseif ($state === 'working')
        <div class="btn-row">
            <form method="post" action="{{ route('attendance.clock_out') }}">
            @csrf
            <button class="btn btn-black" type="submit">退勤</button>
            </form>
            <form method="post" action="{{ route('attendance.break_start') }}">
                @csrf
                <button class="btn btn-white" type="submit">休憩入</button>
            </form>
        </div>
    @elseif ($state === 'breaking')
        <form method="post" action="{{ route('attendance.break_end') }}">
            @csrf
            <button class="btn btn-white" type="submit">休憩戻</button>
        </form>
    @elseif ($state === 'after')
        <p class="msg">お疲れ様でした。</p>
    @endif
</div>

    {{-- フラッシュメッセージ --}}
    @if (session('status')) <p class="flash success">{{ session('status') }}</p> @endif
    @if (session('error'))  <p class="flash error">{{ session('error') }}</p>   @endif
</div>

@endsection