<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleBasedFieldsToDepartmentsAndUsersAndLeaves extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add HOD relationship to departments (check if already exists)
        if (!Schema::hasColumn('departments', 'hod_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->unsignedInteger('hod_id')->nullable()->after('description');
                $table->foreign('hod_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Add department relationship to users (if not exists)
        if (!Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('id');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            });
        }

        // Add leave workflow fields to leaves table
        if (!Schema::hasColumn('leaves', 'status') || !Schema::hasColumn('leaves', 'reason')) {
            Schema::table('leaves', function (Blueprint $table) {
                // Approval workflow fields
                if (!Schema::hasColumn('leaves', 'status')) {
                    $table->string('status')->default('pending')->after('end_date'); // pending, hod_approved, hr_approved, rejected
                }
                if (!Schema::hasColumn('leaves', 'reason')) {
                    $table->text('reason')->nullable()->after('status'); // Leave reason
                }
                if (!Schema::hasColumn('leaves', 'hod_remarks')) {
                    $table->text('hod_remarks')->nullable()->after('reason');
                }
                if (!Schema::hasColumn('leaves', 'hr_remarks')) {
                    $table->text('hr_remarks')->nullable()->after('hod_remarks');
                }
                
                // Approval dates and approvers
                if (!Schema::hasColumn('leaves', 'hod_approved_at')) {
                    $table->timestamp('hod_approved_at')->nullable()->after('hr_remarks');
                }
                if (!Schema::hasColumn('leaves', 'hod_approved_by')) {
                    $table->unsignedInteger('hod_approved_by')->nullable()->after('hod_approved_at');
                    $table->foreign('hod_approved_by')->references('id')->on('users')->onDelete('set null');
                }
                
                if (!Schema::hasColumn('leaves', 'hr_approved_at')) {
                    $table->timestamp('hr_approved_at')->nullable()->after('hod_approved_by');
                }
                if (!Schema::hasColumn('leaves', 'hr_approved_by')) {
                    $table->unsignedInteger('hr_approved_by')->nullable()->after('hr_approved_at');
                    $table->foreign('hr_approved_by')->references('id')->on('users')->onDelete('set null');
                }
                
                // Final approval/rejection
                if (!Schema::hasColumn('leaves', 'final_approved_at')) {
                    $table->timestamp('final_approved_at')->nullable()->after('hr_approved_by');
                }
                if (!Schema::hasColumn('leaves', 'approved_by')) {
                    $table->unsignedInteger('approved_by')->nullable()->after('final_approved_at');
                    $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                }
                
                // Department relationship for leave
                if (!Schema::hasColumn('leaves', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->after('approved_by');
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('leaves')) {
            Schema::table('leaves', function (Blueprint $table) {
                // Drop foreign keys first (with checks)
                try {
                    if (Schema::hasColumn('leaves', 'department_id')) {
                        $table->dropForeign(['department_id']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                try {
                    if (Schema::hasColumn('leaves', 'approved_by')) {
                        $table->dropForeign(['approved_by']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                try {
                    if (Schema::hasColumn('leaves', 'hr_approved_by')) {
                        $table->dropForeign(['hr_approved_by']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                try {
                    if (Schema::hasColumn('leaves', 'hod_approved_by')) {
                        $table->dropForeign(['hod_approved_by']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                // Drop columns (with checks)
                $columnsToRemove = [];
                $possibleColumns = [
                    'status', 'reason', 'hod_remarks', 'hr_remarks',
                    'hod_approved_at', 'hod_approved_by',
                    'hr_approved_at', 'hr_approved_by',
                    'final_approved_at', 'approved_by',
                    'department_id'
                ];
                
                foreach ($possibleColumns as $column) {
                    if (Schema::hasColumn('leaves', $column)) {
                        $columnsToRemove[] = $column;
                    }
                }
                
                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }

        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasColumn('departments', 'hod_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropForeign(['hod_id']);
                $table->dropColumn('hod_id');
            });
        }
    }
}
