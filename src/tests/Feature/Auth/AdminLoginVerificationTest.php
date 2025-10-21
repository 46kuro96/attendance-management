<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminLoginVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ルートが /admin/login の想定。あなたの実装に合わせて変更OK。
    protected string $adminLoginPath = '/admin/login';

    /** @test */
    public function 管理者メール未入力でエラー()
    {
        $res = $this->from($this->adminLoginPath)->post($this->adminLoginPath, [
            'email' => '',
            'password' => 'password',
        ]);

        $res->assertRedirect($this->adminLoginPath);
        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function 管理者パス未入力でエラー()
    {
        $res = $this->from($this->adminLoginPath)->post($this->adminLoginPath, [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $res->assertRedirect($this->adminLoginPath);
        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 管理者_登録と一致しないならエラー()
    {
        $res = $this->from($this->adminLoginPath)->post($this->adminLoginPath, [
            'email' => 'noadmin@example.com',
            'password' => 'password',
        ]);

        $res->assertRedirect($this->adminLoginPath);
        $res->assertSessionHasErrors();
        $this->assertStringContainsString('ログイン情報が登録されていません', collect(session('errors')->all())->implode(' '));
    }
}
