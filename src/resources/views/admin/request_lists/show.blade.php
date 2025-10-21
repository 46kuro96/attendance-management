@extends('layouts.app')

@section('title','修正申請承認画面（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/request_lists/show.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>勤怠詳細</h2>

    <div class="card">
        <table class="show-table">
        <tr>
            <th>名前</th>
            <td class="strong">{{ $vm['name'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>日付</th>
            <td class="date-split">
            <span class="y">{{ $vm['y']  ?? '—' }}</span>
            <span class="md">{{ $vm['md'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <th>出勤・退勤</th>
            <td class="row-flex">
            <span class="pill">{{ $vm['clock_in']  ?? '—' }}</span>
            <span class="tilde">〜</span>
            <span class="pill">{{ $vm['clock_out'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <th>休憩</th>
            <td class="row-flex">
            <span class="pill">{{ $vm['b1s'] ?? '—' }}</span>
            <span class="tilde">〜</span>
            <span class="pill">{{ $vm['b1e'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <th>休憩２</th>
            <td class="row-flex">
            <span class="pill">{{ $vm['b2s'] ?? '—' }}</span>
            <span class="tilde">〜</span>
            <span class="pill">{{ $vm['b2e'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <th>備考</th>
            <td class="note-cell">{{ $vm['note'] ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- 承認ボタンを切り替える --}}
<div class="actions">
    @if(session('approved') || ($vm['status'] ?? '') === 'approved')
        <button type="button" class="btn-disabled" disabled>承認済み</button>
    @elseif(($vm['status'] ?? '') === 'pending')
        <form action="{{ route('admin.request_lists.approve', ['id' => $requestList->id]) }}" method="post" class="inline">
        @csrf
        <button type="submit" class="btn-primary">承認</button>
        </form>
    @endif
    </div>
</div>
@endsection