<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReportScopeFieldsToGeneralReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('general_reports', function (Blueprint $table) {
            $table->enum('report_type', ['general', 'user', 'department'])->default('general')->after('end_date');
            $table->unsignedBigInteger('target_user_id')->nullable()->after('report_type');
            $table->unsignedBigInteger('target_department_id')->nullable()->after('target_user_id');
            
            // Foreign keys commented out for now - add manually if needed
            // $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('target_department_id')->references('id')->on('departments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('general_reports', function (Blueprint $table) {
            // $table->dropForeign(['target_user_id']);
            // $table->dropForeign(['target_department_id']);
            $table->dropColumn(['report_type', 'target_user_id', 'target_department_id']);
        });
    }
}
