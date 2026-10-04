<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trail of a leave request: who did what, at which stage, with what
 * comment. The actor's name and title are copied so the trail still reads
 * correctly after someone changes post or leaves.
 */
class CreateLeaveActionsTable extends Migration
{
    public function up()
    {
        Schema::create('leave_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_id')->index();
            $table->string('stage', 10)->nullable();
            $table->string('action', 20);
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_title')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_actions');
    }
}
