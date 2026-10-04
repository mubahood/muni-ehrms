<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual leave allocated to a person for one leave year: days due plus days
 * carried forward is the most they can take.
 */
class CreateLeaveEntitlementsTable extends Migration
{
    public function up()
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedSmallInteger('leave_year');
            $table->unsignedSmallInteger('days_due')->default(0);
            $table->unsignedSmallInteger('carried_forward')->default(0);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'leave_year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_entitlements');
    }
}
