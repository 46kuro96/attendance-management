<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRequestController;
use App\Http\Controllers\RequestListController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout',[LoginController::class, 'logout'])->name('logout');
Route::get('/register',  [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// 誘導画面（要ログイン）
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// 検証リンク到達（メール内の署名付きURL）
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();               // 認証完了
    return redirect()->route('attendance.create'); // 勤怠登録画面へ
})->middleware(['auth','signed'])->name('verification.verify');

// 認証メールの再送（1分6回まで）
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', '認証メールを再送しました。');
})->middleware(['auth','throttle:6,1'])->name('verification.send');

// MailHogのUIにリダイレクト（開発用）
Route::post('/email/verify/direct', function () {
    return redirect()->away('http://localhost:8025');
})->middleware('auth')->name('verification.direct');

// 一般ユーザー専用
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create'); // 出勤操作画面
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show'); // 表示
    Route::post('/attendance/detail/{id}', [AttendanceController::class, 'requestUpdate'])->name('attendance.request_update');  // 修正申請
    Route::get('/stamp_correction_request/list', [RequestListController::class, 'index'])->name('request_lists.index'); // 申請一覧

    // 操作API
    Route::post('/attendance/clock-in',  [AttendanceController::class, 'clockIn'])->name('attendance.clock_in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock_out');
    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])->name('attendance.break_start');
    Route::post('/attendance/break-end',   [AttendanceController::class, 'breakEnd'])->name('attendance.break_end');
});

// 管理者ログイン
Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login']);
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

// 管理者専用
Route::prefix('admin')->name('admin.')->middleware(['auth','can:admin'])->group(function () {
    // 日次の全ユーザー勤怠一覧（デフォルトは今日）
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendances.index');
    // 勤怠1件の詳細
    Route::get('/attendance/{attendance}', [AdminAttendanceController::class, 'show'])
        ->whereNumber('attendance')->name('attendances.show');
    // 勤怠1件の更新（管理者編集）
    Route::put('/attendance/{attendance}', [AdminAttendanceController::class, 'update'])
        ->whereNumber('attendance')->name('attendances.update');
    // 勤怠1件の承認（管理者承認）
    Route::post('/attendance/{attendance}/approve',[AdminAttendanceController::class, 'approve']
        )->whereNumber('attendance')->name('attendances.approve');
    // スタッフ一覧
    Route::get('/staff/list', [AdminUserController::class, 'index'])
        ->name('staff.list');
    // 指定ユーザーの勤怠一覧
    Route::get('/attendance/staff/{user}', [AdminAttendanceController::class, 'monthly'])
        ->whereNumber('user')->name('staff.monthly');
    // 指定ユーザーの勤怠一覧CSV出力
    Route::get('attendance/staff/{user}/csv', [AdminAttendanceController::class, 'exportCsv'])
        ->whereNumber('user')->name('staff.csv');
    // 申請一覧・詳細・承認
    Route::get('/requests', [AdminRequestController::class, 'index'])->name('request_lists.index');
    Route::get('/requests/{id}', [AdminRequestController::class, 'show'])->whereNumber('id')->name('request_lists.show');
    Route::post('/requests/{id}/approve', [AdminRequestController::class, 'approve'])->whereNumber('id')->name('request_lists.approve');
});