<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            // 管理者をここで弾く（管理者は管理画面へ）
        if (Auth::user()->role === 'admin') {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => '管理者は /admin/login からログインしてください。']);
        }

        // 未認証→誘導（メールも再送）
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
            return redirect()->route('verification.notice');
        }

        // 一般ユーザーは勤怠登録へ
        return redirect()->intended(route('attendance.create'));
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}