<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // ユーザーID（外部キー）
            $table->date('work_date'); // 勤務日
            $table->dateTime('clock_in')->nullable(); // 出勤時間
            $table->dateTime('clock_out')->nullable(); // 退勤時間
            $table->integer('work_minutes')->default(0); // 勤務時間（分）
            $table->text('note')->nullable(); // 備考
            $table->enum('status', ['working', 'completed', 'absent', 'leave'])->default('working'); // 状態
            $table->timestamps();

            $table->unique(['user_id', 'work_date']); // 同じに日に複数勤怠を登録できないようにする
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendances');
    }
}
