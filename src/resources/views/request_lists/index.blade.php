@extends('layouts.app')

@section('title', '申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/request_lists/index.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>申請一覧</h2>

    <!-- タブ -->
    <div class="req-tabs">
        <a href="{{ route('request_lists.index', ['tab' => 'pending']) }}"
        class="tab {{ $tab === 'pending' ? 'is-active' : '' }}">
            承認待ち
        </a>
        <a href="{{ route('request_lists.index', ['tab' => 'approved']) }}"
        class="tab {{ $tab === 'approved' ? 'is-active' : '' }}">
            承認済み
        </a>
    </div>

    @php
        $list = $tab === 'approved' ? $approved : $pending;
    @endphp

    @if($list->count())
    <div class="req-table-wrap">
        <table class="req-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日</th>
                    <th>申請理由</th>
                    <th>{{ $tab === 'approved' ? '承認日時' : '申請日時' }}</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @foreach($list as $req)
                <tr>
                    <td>
                        @if($req->status === 'pending')
                            <span class="badge badge-pending">承認待ち</span>
                        @elseif($req->status === 'approved')
                            <span class="badge badge-approved">承認済み</span>
                        @elseif($req->status === 'rejected')
                            <span class="badge badge-rejected">却下</span>
                        @endif
                    </td>
                    <td>{{ $req->user?->name }}</td>
                    <td>{{ optional($req->attendance?->work_date)->format('Y/m/d') }}</td>
                    <td>{{ $req->reason }}</td>
                    <td>
                        @if($tab === 'approved')
                            {{ optional($req->reviewed_at ?? $req->updated_at)->format('Y/m/d H:i') }}
                        @else
                            {{ optional($req->created_at)->format('Y/m/d H:i') }}
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('attendance.show', ['id' => $req->attendance_id]) }}{{ $req->status === 'pending' ? '?readonly=1' : '' }}">
                            詳細
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <p class="no-req">申請はありません。</p>
    @endif
</div>
@endsection