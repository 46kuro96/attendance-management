<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::where('role','user')->orderBy('id')->get(['id','name','email']);
        return view('admin.staff.index', compact('users'));
    }

    public function monthly(Request $req, User $user)
    {
        $month = $req->filled('month') ? Carbon::parse($req->month.'-01') : Carbon::now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $attendances = Attendance::ofUser($user->id)
            ->whereBetween('work_date', [$start, $end])
            ->orderBy('work_date')->get();

        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        return view('admin.staff.monthly', compact('user','attendances','month','prevMonth','nextMonth'));
    }
}