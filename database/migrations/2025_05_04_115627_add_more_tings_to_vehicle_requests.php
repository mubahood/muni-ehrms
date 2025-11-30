<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreTingsToVehicleRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->string('is_somisy_vehicle')->default('No')->nullable();
            $table->string('is_camp_resident')->default('No')->nullable();
            $table->string('expatirate_type')->default('No')->nullable();
            $table->string('licence_type')->default('Mali')->nullable();
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
