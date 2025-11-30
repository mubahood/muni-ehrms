<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(\App\Models\User::class, 'user_id')->nullable();
            $table->date('attendance_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('status')->default('Missing');
            $table->text('notes')->nullable();
            $table->text('day')->nullable();
            $table->string('is_imported')->default('No');
            $table->string('has_error')->default('No');
            $table->text('error_message')->nullable();
            $table->foreignIdFor(\App\Models\ImportAttendanceRecord::class, 'import_record_id')->nullable();
            //is late
            $table->string('is_late')->default('No');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_records');
    }
}
