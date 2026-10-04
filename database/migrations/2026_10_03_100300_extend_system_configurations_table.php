<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Institution identity for the letterhead, and the rules the attendance engine
 * and leave module work by.
 *
 * The existing row carried placeholder details from another organisation
 * ("Namirembe Road", "recruitment@faras.com"). Only those placeholders, or
 * empty values, are replaced with the University's details — anything an
 * administrator has genuinely set is left alone.
 */
class ExtendSystemConfigurationsTable extends Migration
{
    private const PLACEHOLDERS = ['Namirembe Road', '0800344440', 'recruitment@faras.com', 'MUNI UNIVERSITY'];

    public function up()
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->string('system_name')->default('Electronic Human Resource Management System');
            $table->string('office_name')->default('Office of the University Secretary');
            $table->string('motto')->default('Transforming Lives');
            $table->string('company_fax')->nullable();
            $table->string('company_website')->nullable();
            // ISO-8601 weekday numbers: Monday = 1 … Sunday = 7
            $table->string('working_days', 20)->default('1,2,3,4,5');
            $table->decimal('full_day_hours', 4, 2)->default(8);
            $table->unsignedSmallInteger('repeat_capture_minutes')->default(30);
            $table->unsignedTinyInteger('leave_year_start_month')->default(7);
            $table->unsignedSmallInteger('annual_leave_days')->default(30);
            $table->string('report_footer')->default('Human Resource Office – confidential staff record');
        });

        $defaults = [
            'company_name' => 'Muni University',
            'company_address' => 'P.O. Box 725 Arua, Uganda',
            'company_phone' => '+256 476 420312/3/4',
            'company_email' => 'info@muni.ac.ug',
        ];
        foreach (DB::table('system_configurations')->get() as $row) {
            $update = ['company_fax' => '+256 476 420316', 'company_website' => 'www.muni.ac.ug'];
            foreach ($defaults as $column => $value) {
                $current = trim((string) $row->{$column});
                if ($current === '' || in_array($current, self::PLACEHOLDERS, true)) {
                    $update[$column] = $value;
                }
            }
            DB::table('system_configurations')->where('id', $row->id)->update($update);
        }
    }

    public function down()
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'system_name', 'office_name', 'motto', 'company_fax', 'company_website', 'working_days',
                'full_day_hours', 'repeat_capture_minutes', 'leave_year_start_month', 'annual_leave_days',
                'report_footer',
            ]);
        });
    }
}
