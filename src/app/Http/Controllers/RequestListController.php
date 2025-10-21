<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RequestList;

class RequestListController extends Controller
{
    public function index(Request $request)
    {
        $uid = Auth::id();
        $tab = $request->input('tab', 'pending');

        // 共通クエリ
        $base = RequestList::with(['attendance', 'attendance.user'])
            ->where('user_id', $uid)
            ->latest('created_at');

        // タブ別
        $pending  = (clone $base)->where('status', 'pending')->paginate(10, ['*'], 'pending_page');
        $approved = (clone $base)->where('status', 'approved')->paginate(10, ['*'], 'approved_page');

        return view('request_lists.index', compact('pending','approved','tab'));
    }
}
