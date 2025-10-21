<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    protected function admin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function 全一般ユーザーの氏名とメールを確認できる()
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['role' => 'user']);

        $res = $this->actingAs($admin)->get(route('admin.staff.list'));
        $res->assertStatus(200);

        User::where('role', 'user')->get()->each(function ($u) use ($res) {
            $res->assertSee($u->name);
            $res->assertSee($u->email);
        });
    }

    /** @test */
    public function 特定ユーザーの勤怠一覧ページで情報が表示される()
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['role' => 'user']);

        $res = $this->actingAs($admin)->get(route('admin.staff.monthly', $user->id));

        $res->assertStatus(200);
        $res->assertSee($user->name);
    }
}
