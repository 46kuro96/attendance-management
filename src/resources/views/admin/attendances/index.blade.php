@extends('layouts.app')

@section('title', '勤怠一覧(管理者)')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendances/index.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>{{ $day->format('Y年n月j日') }}の勤怠</h2>

    <div class="date-nav">
        <a href="{{ route('admin.attendances.index', ['date'=>$prev]) }}">← 前日</a>
        <form action="{{ route('admin.attendances.index') }}" method="get">
            @csrf
            <img src="{{ asset('image/image.png') }}" alt="calendar icon" class="calendar-icon">
            <input type="date" name="date" value="{{ $day->format('Y-m-d') }}" onchange="this.form.submit()">
        </form>
        <a href="{{ route('admin.attendances.index', ['date'=>$next]) }}">翌日 →</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>名前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($attendances as $att)
            @php
            // 休憩合計（分→H:MM）
            $breakMin  = (int) $att->breaks->sum('minutes');
            $breakText = $breakMin > 0 ? sprintf('%d:%02d', intdiv($breakMin,60), $breakMin%60) : '';

            // 実働合計
            $workText = $att->work_hours_text;
            @endphp
        <tr>
            <td>{{ $att->user->name }}</td>
            <td>{{ optional($att->clock_in)->format('H:i') }}</td>
            <td>{{ optional($att->clock_out)->format('H:i') }}</td>
            <td>{{ $breakText }}</td>
            <td>{{ $workText }}</td>
            <td><a class="link" href="{{ route('admin.attendances.show', $att->id) }}">詳細</a></td>
        </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center;">データがありません。</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection