<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMailsToVehicleRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->string('mail_sent_to_hod')->default('No');
            $table->string('mail_sent_to_gm')->default('No');
            $table->string('mail_sent_to_security_exit')->default('No');
            $table->string('mail_sent_to_security_return')->default('No');
            $table->string('mail_sent_to_applicant_on_hod_approval')->default('No');
            $table->string('mail_sent_to_applicant_on_gm_approval')->default('No');
            $table->string('mail_sent_to_applicant_on_security_exit_approval')->default('No');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            //
        });
    }
}
