@extends('layouts.app')

@section('title', '申請一覧(管理者)')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/request_lists/index.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>申請一覧</h2>

    {{-- タブ --}}
    <div class="req-tabs">
        <a href="{{ route('admin.request_lists.index', ['tab' => 'pending']) }}"
        class="tab {{ $tab === 'pending' ? 'is-active' : '' }}">承認待ち</a>
        <a href="{{ route('admin.request_lists.index', ['tab' => 'approved']) }}"
        class="tab {{ $tab === 'approved' ? 'is-active' : '' }}">承認済み</a>
    </div>

    {{-- テーブル --}}
    <table class="req-table">
        <thead>
        <tr>
            <th>状態</th>
            <th>名前</th>
            <th>対象日時</th>
            <th>申請理由</th>
            <th>申請日時</th>
            <th>詳細</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
            <td>
                <span class="status-badge {{ $r->status }}">
                {{ $r->status === 'pending' ? '承認待ち' : ($r->status === 'approved' ? '承認済み' : '却下') }}
                </span>
            </td>
            <td>{{ $r->user->name ?? '—' }}</td>
            <td>
                {{-- 勤怠が無い可能性も考慮 --}}
                {{ optional(optional($r->attendance)->work_date)->format('Y/m/d') ?? '—' }}
            </td>
            <td class="reason-cell">{{ $r->reason ?? '—' }}</td>
            <td>{{ optional($r->created_at)->format('Y/m/d') }}</td>
            <td>
                <a class="link-detail" href="{{ route('admin.request_lists.show', ['id' => $r->id]) }}">詳細</a>
            </td>
            </tr>
        @empty
            <tr>
            <td colspan="6" class="empty">表示できる申請はありません。</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection