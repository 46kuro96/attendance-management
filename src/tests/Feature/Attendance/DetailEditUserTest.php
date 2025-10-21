<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;

class DetailEditUserTest extends TestCase
{
    use RefreshDatabase;

    private function signedInVerifiedUser(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($u);
        return $u;
    }

    // $date は 'Y-m-d' を期待
    protected function makeData(string $date, array $overrides = []): array
    {
        return array_merge([
            'work_date' => $date,
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
            'reason'    => '申請理由メモ',
        ], $overrides);
    }

    /** @test */
    public function 出勤が退勤より後ならエラー()
    {
        $user = $this->signedInVerifiedUser();
        $att  = Attendance::factory()->for($user)->create();
        $date = $att->work_date->toDateString();

        $res = $this->from(route('attendance.show', $att->id))
            ->post(route('attendance.request_update', $att->id), $this->makeData($date, [
                'clock_in'  => '20:00',
                'clock_out' => '18:00',
            ]));

        $res->assertRedirect(route('attendance.show', $att->id));
        $res->assertSessionHasErrors(['clock_in', 'clock_out']);
        $this->assertStringContainsString('出勤時間もしくは退勤時間が不適切な値です', session('errors')->first('clock_in'));
    }

    /** @test */
    public function 休憩開始が退勤より後ならエラー()
    {
        $user = $this->signedInVerifiedUser();
        $att  = Attendance::factory()->for($user)->create();
        $date = $att->work_date->toDateString();

        $payload = $this->makeData($date, [
            'clock_out' => '18:00',
            'breaks'    => [['start' => '23:00', 'end' => '23:30']],
        ]);

        $res = $this->from(route('attendance.show', $att->id))
            ->post(route('attendance.request_update', $att->id), $payload);

        $res->assertRedirect(route('attendance.show', $att->id));
        $res->assertSessionHasErrors(['breaks.0.start']);
        $this->assertStringContainsString('休憩時間が不適切な値です', session('errors')->first('breaks.0.start'));
    }

    /** @test */
    public function 休憩終了が退勤より後ならエラー()
    {
        $user = $this->signedInVerifiedUser();
        $att  = Attendance::factory()->for($user)->create();
        $date = $att->work_date->toDateString();

        $payload = $this->makeData($date, [
            'clock_out' => '18:00',
            'breaks'    => [['start' => '17:30', 'end' => '23:30']],
        ]);

        $res = $this->from(route('attendance.show', $att->id))
            ->post(route('attendance.request_update', $att->id), $payload);

        $res->assertRedirect(route('attendance.show', $att->id));
        $res->assertSessionHasErrors(['breaks.0.end']);
        $this->assertStringContainsString('休憩時間もしくは退勤時間が不適切な値です', session('errors')->first('breaks.0.end'));
    }

    /** @test */
    public function 備考未入力でエラー()
    {
        $user = $this->signedInVerifiedUser();
        $att  = Attendance::factory()->for($user)->create();
        $date = $att->work_date->toDateString();

        $res = $this->from(route('attendance.show', $att->id))
            ->post(route('attendance.request_update', $att->id), $this->makeData($date, ['reason' => '']));

        $res->assertRedirect(route('attendance.show', $att->id));
        $res->assertSessionHasErrors(['reason']);
        $this->assertStringContainsString('備考を記入してください', session('errors')->first('reason'));
    }

    /** @test */
    public function 修正申請が作成され管理者画面に出る()
    {
        $user = $this->signedInVerifiedUser();
        $att  = Attendance::factory()->for($user)->create();
        $date = $att->work_date->toDateString();

        $res = $this->from(route('attendance.show', $att->id))
            ->post(route('attendance.request_update', $att->id), $this->makeData($date));

        $res->assertRedirect(route('attendance.show', $att->id));

        // 実テーブル名に合わせて（あなたの実装は request_lists ）
        $this->assertDatabaseHas('request_lists', [
            'attendance_id' => $att->id,
            'status'        => 'pending',
        ]);
    }
}