@extends('layouts.app')

@section('title', '勤怠詳細(管理者)')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/attendances/show.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>勤怠詳細</h2>

    <form action="{{ route('admin.attendances.update', $attendance->id) }}" method="post">
        @csrf
        @method('put')

        <input type="hidden" name="work_date" value="{{ $attendance->work_date->toDateString() }}">

        <table class="form-table">
            <tr>
                <th>名前</th>
                <td>{{ $attendance->user->name }}</td>
            </tr>
            <tr>
                <th>日付</th>
                <td class="date-split">
                    <span class="y">{{ $attendance->work_date->format('Y年') }}</span>
                    <span class="md">{{ $attendance->work_date->format('n月j日') }}</span>
                </td>
            </tr>
            <tr>
                <th>出勤・退勤</th>
                <td class="hor">
                    <input type="time" name="clock_in"
                    value="{{ old('clock_in', $attendance->clock_in ? $attendance->clock_in->format('H:i') : '') }}">
                    @error('clock_in')
                        <div class="error">{{ $message }}</div>
                    @enderror
                    <span class="sep">〜</span>
                    <input type="time" name="clock_out"
                    value="{{ old('clock_out', $attendance->clock_out ? $attendance->clock_out->format('H:i') : '') }}">
                    @error('clock_out')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </td>
            </tr>

            <tr>
                <th>休憩</th>
                <td class="hor">
                    <input type="time" name="breaks[0][start]"
                    value="{{ old('breaks.0.start', ($b1 && $b1->start) ? $b1->start->format('H:i') : '') }}">
                    @error('breaks.0.start')
                        <div class="error">{{ $message }}</div>
                    @enderror
                    <span class="sep">〜</span>
                    <input type="time" name="breaks[0][end]"
                    value="{{ old('breaks.0.end',   ($b1 && $b1->end)   ? $b1->end->format('H:i')   : '') }}">
                    @error('breaks.0.end')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </td>
            </tr>

            <tr>
                <th>休憩２</th>
                <td class="hor">
                    <input type="time" name="breaks[1][start]"
                    value="{{ old('breaks.1.start', ($b2 && $b2->start) ? $b2->start->format('H:i') : '') }}">
                    @error('breaks.1.start')
                        <div class="error">{{ $message }}</div>
                    @enderror
                    <span class="sep">〜</span>
                    <input type="time" name="breaks[1][end]"
                    value="{{ old('breaks.1.end',   ($b2 && $b2->end)   ? $b2->end->format('H:i')   : '') }}">
                    @error('breaks.1.end')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </td>
            </tr>

            <tr>
                <th>備考</th>
                <td>
                    <textarea  name="note" class="text">{{ old('note', $attendance->note ?? '') }}</textarea>
                    @error('note')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </td>
            </tr>
        </table>

        <div class="button">
            <button type="submit" class="btn-black">修正</button>
        </div>
    </form>
</div>
@endsection