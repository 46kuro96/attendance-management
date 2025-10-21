<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Models\User;
use Illuminate\Support\Facades\URL;


class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後に認証メールが送られる()
    {
        Notification::fake();

        $res = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** @test */
    public function 誘導画面からボタン押下で認証サイトへ遷移する()
    {
        // 誘導画面のルート例: /email/verify (Fortify標準)
        $res = $this->actingAs(User::factory()->unverified()->create())
                    ->get('/email/verify');

        $res->assertStatus(200);
        $res->assertSee('認証はこちらから');
    }

    /** @test */
    public function 認証完了で勤怠登録画面へリダイレクト()
    {
        $user = User::factory()->unverified()->create();

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify', // Fortifyの標準名
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $res = $this->actingAs($user)->get($verifyUrl);

        $res->assertRedirect(route('attendance.create'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
