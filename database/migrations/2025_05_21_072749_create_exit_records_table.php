<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExitRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exit_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(\App\Models\User::class, 'employee_id')->nullable();
            $table->foreignIdFor(\App\Models\VehicleRequest::class, 'vehicle_request_id')->nullable();
            $table->foreignIdFor(\App\Models\User::class, 'created_by_id')->nullable();
            $table->string('status')->default('exit');
            $table->string('remarks')->nullable();
            $table->dateTime('exit_time')->nullable();
            $table->dateTime('return_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exit_records');
    }
}
