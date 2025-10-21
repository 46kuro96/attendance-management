@extends('layouts.app')

@section('title', $user->name . 'さんの勤怠')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendances/staff_show.css') }}">
@endsection

@section('content')
<div class="att-page">
    <div class="att-header">
        <h2>{{ $user->name }}さんの勤怠</h2>
        </div>
    </div>

    <div class="list-nav">
        <a class="nav-btn" href="{{ route('admin.staff.monthly',['user'=>$user->id, 'month'=>$prevYm]) }}">← 前月</a>
        <div class="ym"><span><img class="img" src="{{ asset('image/image.png') }}" alt="calender"></span> {{ $ym }}</div>
        <a class="nav-btn" href="{{ route('admin.staff.monthly',['user'=>$user->id, 'month'=>$nextYm]) }}">翌月 →</a>
    </div>

    <div class="table">
        <div class="thead">
        <div>日付</div><div>出勤</div><div>退勤</div><div>休憩</div><div>合計</div><div>詳細</div>
        </div>
        @foreach($days as $d)
        <div class="row">
            <div>{{ $d['date']->isoformat('MM/DD(ddd)') }}</div>
            <div>{{ $d['clock_in']  ?? '' }}</div>
            <div>{{ $d['clock_out'] ?? '' }}</div>
            <div>{{ $d['break_total'] ?? '' }}</div>
            <div>{{ $d['work_total']  ?? '' }}</div>
            <div>
            @if($d['attendance'])
                <a class="link" href="{{ route('attendance.show',$d['attendance']->id) }}">詳細</a>
            @endif
            </div>
        </div>
        @endforeach
    </div>
    <div class="csv-button">
        <a href="{{ route('admin.staff.csv', ['user' => $user->id, 'month' => $ym]) }}" class='csv'>CSV出力</a>
    </div>
</div>
@endsection