<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class RegisterVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 名前が未入力ならエラー()
    {
        $res = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $res->assertRedirect('/register');
        $res->assertSessionHasErrors(['name']);
        $this->assertStringContainsString('お名前を入力してください', session('errors')->first('name'));
    }

    /** @test */
    public function メールアドレスが未入力ならエラー()
    {
        $res = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $res->assertRedirect('/register');
        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function パスワードが8文字未満ならエラー()
    {
        $res = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $res->assertRedirect('/register');
        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString('パスワードは8文字以上で入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function パスワード不一致ならエラー()
    {
        $res = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ]);

        $res->assertRedirect('/register');
        $res->assertSessionHasErrors(['password_confirmation']);
        $this->assertStringContainsString('パスワードと一致しません', session('errors')->first('password_confirmation'));
    }

    /** @test */
    public function パスワード未入力ならエラー()
    {
        $res = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $res->assertRedirect('/register');
        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 正しく入力すれば保存される()
    {
        $res = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // 成功時のリダイレクト先はプロジェクトの仕様に合わせて
        $res->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name'  => '山田太郎',
        ]);
    }
}
