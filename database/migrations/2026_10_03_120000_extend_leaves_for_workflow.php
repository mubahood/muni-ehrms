<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `leaves` into the University's leave application: Section I (the
 * request), the approval route and current stage, Section II (HR's computation,
 * frozen when HR verifies) and Section III (the decision), plus recall.
 *
 * The older approval columns (hod_remarks, hr_approved_at, …) are left in place
 * for existing rows; the full trail now lives in `leave_actions`.
 */
class ExtendLeavesForWorkflow extends Migration
{
    public function up()
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('id');
            $table->unsignedSmallInteger('days')->default(0)->after('end_date');
            $table->unsignedSmallInteger('leave_year')->nullable()->index()->after('days');
            $table->date('return_date')->nullable()->after('leave_year');
            $table->string('source', 20)->default('application')->after('status');
            $table->string('route', 40)->nullable()->after('source');
            $table->string('stage', 10)->nullable()->index()->after('route');
            $table->unsignedInteger('acting_user_id')->nullable()->after('reason');
            $table->string('contact_address')->nullable()->after('acting_user_id');
            $table->string('contact_phone', 40)->nullable()->after('contact_address');
            $table->unsignedInteger('recorded_by')->nullable()->after('contact_phone');
            $table->timestamp('submitted_at')->nullable()->after('recorded_by');
            $table->timestamp('decided_at')->nullable()->after('submitted_at');
            // Section II, frozen by Human Resource
            $table->smallInteger('hr_days_due')->nullable();
            $table->smallInteger('hr_carried_forward')->nullable();
            $table->smallInteger('hr_days_taken')->nullable();
            $table->smallInteger('hr_balance')->nullable();
            // Recall from leave
            $table->date('recall_date')->nullable();
            $table->text('recall_reason')->nullable();
            $table->unsignedInteger('recalled_by')->nullable();
            $table->timestamp('recalled_at')->nullable();
            $table->unsignedSmallInteger('days_restored')->default(0);
            $table->index(['user_id', 'status']);
        });

        // "Casual" is not a type on the University's form.
        DB::table('leaves')->where('leave_type', 'casual')->update(['leave_type' => 'other']);
    }

    public function down()
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['leave_year']);
            $table->dropIndex(['stage']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn([
                'reference', 'days', 'leave_year', 'return_date', 'source', 'route', 'stage', 'acting_user_id',
                'contact_address', 'contact_phone', 'recorded_by', 'submitted_at', 'decided_at', 'hr_days_due',
                'hr_carried_forward', 'hr_days_taken', 'hr_balance', 'recall_date', 'recall_reason', 'recalled_by',
                'recalled_at', 'days_restored',
            ]);
        });
    }
}
