<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;

class AttendanceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function admin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function 当日の全ユーザー勤怠が見える()
    {
        $admin = $this->admin();
        Attendance::factory()->count(5)->create(); // 適当に5件

        $res = $this->actingAs($admin)->get(route('admin.attendances.index', [
            'date' => now()->toDateString()
        ]));

        $res->assertStatus(200);
        $res->assertSee(now()->format('Y-m-d'));
    }

    /** @test */
    public function 前日へ遷移()
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->get(route('admin.attendances.index', [
            'date' => now()->subDay()->toDateString()
        ]));

        $res->assertStatus(200);
        $res->assertSee(now()->subDay()->format('Y-m-d'));
    }

    /** @test */
    public function 翌日へ遷移()
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->get(route('admin.attendances.index', [
            'date' => now()->addDay()->toDateString()
        ]));

        $res->assertStatus(200);
        $res->assertSee(now()->addDay()->format('Y-m-d'));
    }

    /** @test */
    public function 詳細画面は選択した情報が表示される()
    {
        $admin = $this->admin();
        $att = Attendance::factory()->create();

        $res = $this->actingAs($admin)->get(route('admin.attendances.show', $att->id));
        $res->assertStatus(200);
        $this->assertStringContainsString(''.$att->work_date->year.'年', $res->getContent());
        $this->assertStringContainsString($att->work_date->format('n月j日'), $res->getContent());
    }

    /** @test */
    public function 管理者が承認すると勤怠が更新される()
    {
        $admin = $this->admin();
        $att = Attendance::factory()->working()->create();

        // 申請の詳細画面で承認POSTの想定
        $res = $this->actingAs($admin)->post(route('admin.attendances.approve', $att->id));
        $res->assertRedirect(route('admin.attendances.show', $att->id));

        $att->refresh();
    $this->assertSame('completed', $att->status);
    }
}
