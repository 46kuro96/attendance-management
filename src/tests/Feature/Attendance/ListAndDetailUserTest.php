<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ListAndDetailUserTest extends TestCase
{
    use RefreshDatabase;

    private function signedInVerifiedUser(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($u);
        return $u;
    }

    private function seedMonth(User $user, string $ym): array
    {
        // その月の全日をユニークな work_date で作成して、重複を回避
        $start = Carbon::createFromFormat('Y-m-d', $ym.'-01');
        $end   = (clone $start)->endOfMonth();
        $ids   = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $att = Attendance::factory()
                ->for($user)
                ->state(['work_date' => $day->toDateString()])
                ->create();

            $ids[] = $att->id;
        }
        return $ids;
    }

    /** @test */
    public function 自分の勤怠一覧が全て表示される()
    {
        $user = $this->signedInVerifiedUser();
        $ym   = now()->format('Y-m');
        $this->seedMonth($user, $ym);

        $res = $this->get(route('attendance.index'));
        $res->assertOk();

        // ヘッダー部の月表示（実装は "YYYY/MM"）
        $res->assertSee(now()->format('Y/m'));
        // 1日分の行断片（例：10/01(…）
        $res->assertSee(now()->startOfMonth()->format('m/d'));
    }

    /** @test */
    public function 一覧初期表示は当月()
    {
        $user = $this->signedInVerifiedUser();
        $ym   = now()->format('Y-m');
        $this->seedMonth($user, $ym);

        $res = $this->get(route('attendance.index'));
        $res->assertOk();
        $res->assertSee(now()->format('Y/m')); // ← 実装に合わせる
    }

    /** @test */
    public function 前月ボタンで前月へ切替()
    {
        $user = $this->signedInVerifiedUser();
        $ym   = now()->subMonth()->format('Y-m');
        $this->seedMonth($user, $ym);

        // 実装はクエリキーが "ym"
        $res = $this->get(route('attendance.index', ['ym' => $ym]));
        $res->assertOk();
        $res->assertSee(now()->subMonth()->format('Y/m'));
    }

    /** @test */
    public function 翌月ボタンで翌月へ切替()
    {
        $user = $this->signedInVerifiedUser();
        $ym   = now()->addMonth()->format('Y-m');
        $this->seedMonth($user, $ym);

        $res = $this->get(route('attendance.index', ['ym' => $ym]));
        $res->assertOk();
        $res->assertSee(now()->addMonth()->format('Y/m'));
    }

    /** @test */
    public function 詳細ボタンで該当日の詳細に遷移()
    {
        $user = $this->signedInVerifiedUser();
        // 詳細対象を1件だけ用意（ユニーク制約を避けるため日付指定）
        $att  = Attendance::factory()
            ->for($user)
            ->state(['work_date' => now()->toDateString()])
            ->create();

        $res = $this->get(route('attendance.show', $att->id));
        $res->assertOk();

        // 実装は hidden value="YYYY-MM-DD" と和式分割表示
        $res->assertSee('name="work_date"', false);
        $res->assertSee('value="'.$att->work_date->toDateString().'"', false);
        $res->assertSee($att->work_date->year.'年');
        $res->assertSee($att->work_date->format('n月j日'));
    }
}