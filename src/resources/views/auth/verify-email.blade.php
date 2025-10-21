@extends('layouts.app')

@section('title','メール認証誘導画面')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/verify.css') }}">
@endsection

@section('content')
<div class="verify-wrap">
    <p class="lead">
        登録していただいたメールアドレスに認証メールを送付しました。<br>
        メール認証を完了してください。
    </p>

    {{-- localhost:8025に遷移 --}}
    <form method="post" action="{{ route('verification.direct') }}" class="verify-main">
        @csrf
        <button type="submit" class="btn-primary">認証はこちらから</button>
    </form>

    {{-- 下部に再送リンク --}}
    <form method="post" action="{{ route('verification.send') }}" class="verify-resend">
        @csrf
        <button type="submit" class="link-like">認証メールを再送する</button>
    </form>

    @if (session('message'))
        <p class="flash">{{ session('message') }}</p>
    @endif
</div>
@endsection