<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hours were stored as whole numbers, so 7 h 45 m was saved as 7 or 8.
 * Two decimal places keep minutes (0.25 h = 15 min).
 */
class MakeAttendanceHoursDecimal extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE attendance_records MODIFY hours DECIMAL(5,2) NOT NULL DEFAULT 0');
    }

    public function down()
    {
        DB::statement('ALTER TABLE attendance_records MODIFY hours INT NOT NULL DEFAULT 0');
    }
}
