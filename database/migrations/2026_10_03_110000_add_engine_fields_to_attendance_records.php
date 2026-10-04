<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the attendance engine fills when it rebuilds a day from the clock-ins,
 * plus the record of an HR correction (a corrected day is never rebuilt).
 *
 * One record per person per day is enforced so concurrent clock-ins cannot
 * create two.
 */
class AddEngineFieldsToAttendanceRecords extends Migration
{
    public function up()
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('is_late');
            $table->unsignedSmallInteger('punch_count')->default(0)->after('late_minutes');
            $table->boolean('is_working_day')->default(true)->after('punch_count');
            $table->boolean('is_half_day')->default(false)->after('is_working_day');
            $table->string('holiday_name')->nullable()->after('is_half_day');
            $table->boolean('is_manual')->default(false)->after('source');
            $table->unsignedInteger('corrected_by')->nullable()->after('is_manual');
            $table->timestamp('corrected_at')->nullable()->after('corrected_by');
            $table->text('correction_reason')->nullable()->after('corrected_at');
            $table->unique(['user_id', 'attendance_date'], 'attendance_user_date_unique');
        });
    }

    public function down()
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique('attendance_user_date_unique');
            $table->dropColumn([
                'late_minutes', 'punch_count', 'is_working_day', 'is_half_day', 'holiday_name',
                'is_manual', 'corrected_by', 'corrected_at', 'correction_reason',
            ]);
        });
    }
}
