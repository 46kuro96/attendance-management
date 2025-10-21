<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @hasSection('title')
            @yield('title')
        @endif
    </title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('css')
</head>

<body>
<header class="header">
    <div class="header-inner">
        <div class="logo">
            <a href="@auth{{ auth()->user()->can('admin') ? route('admin.attendances.index') : route('attendance.index') }}
            @else
                {{ route('login') }}
            @endauth">
                <img src="{{ asset('image/logo.png') }}" alt="COACHTECH">
            </a>
        </div>

        @if (!in_array(Route::currentRouteName(), ['login', 'register', 'admin.login']))
        <nav class="nav-links">
            @auth
                @if(Auth::user()->role === 'admin')
                    {{-- 管理者用メニュー --}}
                    <a href="{{ route('admin.attendances.index') }}">勤怠一覧</a>
                    <a href="{{ route('admin.staff.list') }}">スタッフ一覧</a>
                    <a href="{{ route('admin.request_lists.index') }}">申請一覧</a>
                    <form action="{{ route('admin.logout') }}" method="post" style="display:inline;">
                        @csrf
                        <button type="submit">ログアウト</button>
                    </form>
                @else
                    {{-- 一般ユーザー用メニュー --}}
                    <a href="{{ route('attendance.create') }}">勤怠</a>
                    <a href="{{ route('attendance.index') }}">勤怠一覧</a>
                    <a href="{{ route('request_lists.index') }}">申請</a>

                    <form action="{{ route('logout') }}" method="post" style="display:inline;">
                        @csrf
                        <button type="submit">ログアウト</button>
                    </form>
                @endif
            @else
                {{-- 未ログイン時 --}}
                <a href="{{ route('login') }}">ログイン</a>
                <a href="{{ route('register') }}">会員登録</a>
            @endauth
        </nav>
        @endif
    </div>
</header>

    <main>
        @yield('content')
    </main>
</body>

</html>