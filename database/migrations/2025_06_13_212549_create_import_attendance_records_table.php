<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportAttendanceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('file_path')->nullable();
            $table->string('status')->default('Pending');
            $table->string('is_imported')->default('No');
            $table->string('has_error')->default('No');
            $table->text('error_message')->nullable();
            $table->foreignIdFor(User::class, 'user_id')->nullable();
            $table->text('due_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('import_attendance_records');
    }
}
