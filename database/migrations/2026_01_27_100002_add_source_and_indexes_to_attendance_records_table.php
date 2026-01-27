<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceAndIndexesToAttendanceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Add source column if it doesn't exist
            if (!Schema::hasColumn('attendance_records', 'source')) {
                $table->string('source')->default('system')->after('is_late')->comment('Source of attendance: system|device|import');
            }
            
            // Add performance indexes
            $table->index(['user_id', 'attendance_date'], 'idx_user_date');
            $table->index(['status', 'attendance_date'], 'idx_status_date');
            $table->index(['attendance_date'], 'idx_attendance_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_user_date');
            $table->dropIndex('idx_status_date');
            $table->dropIndex('idx_attendance_date');
            
            // Drop source column if needed
            if (Schema::hasColumn('attendance_records', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
}
