<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceGeneralReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('general_reports', function (Blueprint $table) {
            // Add new columns
            $table->string('status')->default('pending')->after('is_generated')
                ->comment('pending, processing, completed, failed');
            $table->string('report_type')->default('general')->after('status')
                ->comment('general, custom, summary');
            $table->unsignedBigInteger('user_id')->nullable()->after('report_type')
                ->comment('User who created the report');
            $table->text('description')->nullable()->after('user_id')
                ->comment('Report description or notes');
            $table->integer('total_employees')->nullable()->after('description')
                ->comment('Number of employees in report');
            $table->integer('total_records')->nullable()->after('total_employees')
                ->comment('Total attendance records processed');
            $table->decimal('generation_time', 8, 2)->nullable()->after('total_records')
                ->comment('Time taken to generate report in seconds');
            $table->text('error_message')->nullable()->after('generation_time')
                ->comment('Error message if generation failed');

            // Add indexes for better query performance
            $table->index('status');
            $table->index('is_generated');
            $table->index('report_type');
            $table->index(['start_date', 'end_date']);
            $table->index('created_at');
            $table->index('user_id');
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
            // Drop indexes first
            $table->dropIndex(['status']);
            $table->dropIndex(['is_generated']);
            $table->dropIndex(['report_type']);
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);

            // Drop columns
            $table->dropColumn([
                'status',
                'report_type',
                'user_id',
                'description',
                'total_employees',
                'total_records',
                'generation_time',
                'error_message',
            ]);
        });
    }
}
