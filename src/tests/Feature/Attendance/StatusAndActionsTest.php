<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;


class StatusAndActionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 現在の日時がUIと同形式で出る()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        $res = $this->get(route('attendance.create'));

        $res->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/\d{4}年\d{1,2}月\d{1,2}日[((][日月火水木金土][))]/u',
        $res->getContent());
    }

    /** @test */
    public function ステータス_勤務外()
    {
        $user = User::factory()->create();
        // 勤務外＝当日Attendance無し、などのあなたの定義に合わせてOK
        $res = $this->actingAs($user)->get(route('attendance.create'));

        $res->assertStatus(200);
        $res->assertSee('勤務外');
    }

    /** @test */
    public function 出勤ボタンで勤務中になる()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('attendance.clock_in')); // 出勤POST
        $res = $this->actingAs($user)->get(route('attendance.create'));

        $res->assertSee('出勤中'); // 画面の文言に合わせて
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status'  => 'working',
        ]);
    }

    /** @test */
    public function 出勤は一日一回のみ()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('attendance.clock_in'));
        $res = $this->actingAs($user)->post(route('attendance.clock_in'));

        // 2回目はボタン非表示やエラー表示など、あなたの仕様に合わせて判定
        $res->assertSessionHas('error'); // 例
    }

    /** @test */
    public function 休憩入_休憩中表示_何回でも可()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('attendance.clock_in'));

        $this->actingAs($user)->post(route('attendance.break_start'));
        $res = $this->actingAs($user)->get(route('attendance.create'));

        $res->assertSee('休憩中');

        // 再度、戻→入 を繰り返してもOK
        $this->actingAs($user)->post(route('attendance.break_end'));
        $this->actingAs($user)->post(route('attendance.break_start'));

        $res = $this->actingAs($user)->get(route('attendance.create'));
        $res->assertSee('休憩中');
    }

    /** @test */
    public function 退勤で退勤済表示()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('attendance.clock_in'));

        $this->actingAs($user)->post(route('attendance.clock_out'));
        $res = $this->actingAs($user)->get(route('attendance.create'));

        $res->assertSee('退勤済');
    }
}
