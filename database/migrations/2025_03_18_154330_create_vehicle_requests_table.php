<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignIdFor(Vehicle::class, 'vehicle_id')->nullable();
            $table->foreignIdFor(User::class, 'applicant_id')->nullable();
 

            // Trip details
            $table->dateTime('requested_departure_time')->nullable();
            $table->dateTime('requested_return_time')->nullable();
            $table->dateTime('actual_return_time')->nullable();
            $table->dateTime('actual_departure_time')->nullable();
            $table->text('destination')->nullable();

            // Justification or reason for the request
            $table->text('justification')->nullable();

            // Status workflow: 'pending', 'approved', 'rejected', etc.
            $table->string('status')->default('Pending');
            $table->string('hod_status')->default('Pending');
            $table->string('gm_status')->default('Pending');
            $table->string('security_exit_status')->default('Pending');
            $table->string('security_return_status')->default('Pending');
            $table->string('return_state')->nullable();
            $table->string('over_stayed')->nullable()->default('No');
            $table->string('exit_state')->nullable();
            $table->text('exit_comment')->nullable();
            $table->text('return_comment')->nullable();
            $table->text('hod_comment')->nullable();
            $table->text('gm_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_requests');
    }
}
