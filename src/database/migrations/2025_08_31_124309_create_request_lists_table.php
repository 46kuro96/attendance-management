<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('request_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // ユーザーID（外部キー）
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete(); // 勤怠ID（外部キー）
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete(); // 管理者削除時はnullに
            $table->json('before_payload'); // 変更前のデータ
            $table->json('after_payload'); // 変更後のデータ
            $table->text('reason'); // 理由
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // 状態
            $table->dateTime('reviewed_at')->nullable(); // 承認・却下日時
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('request_lists');
    }
}
