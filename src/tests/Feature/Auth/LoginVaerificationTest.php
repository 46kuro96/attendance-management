<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function メール未入力でエラー()
    {
        $res = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $res->assertRedirect('/login');
        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function パスワード未入力でエラー()
    {
        $res = $this->from('/login')->post('/login', [
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $res->assertRedirect('/login');
        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 登録内容と一致しないならメッセージ()
    {
        // 存在しないメールでログイン
        $res = $this->from('/login')->post('/login', [
            'email' => 'nouser@example.com',
            'password' => 'password',
        ]);

        $res->assertRedirect('/login');
        $res->assertSessionHasErrors();
        $this->assertStringContainsString('ログイン情報が登録されていません', collect(session('errors')->all())->implode(' '));
    }
}
