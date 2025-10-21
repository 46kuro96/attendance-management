<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovalLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('request_lists')->cascadeOnDelete(); // 申請ID（外部キー）
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete(); // 承認者ID（外部キー）
            $table->enum('action',['submitted','approved','rejected']); // アクション
            $table->text('comment')->nullable(); // コメント
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
        Schema::dropIfExists('approval_logs');
    }
}
