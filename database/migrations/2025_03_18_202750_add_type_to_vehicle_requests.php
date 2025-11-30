<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToVehicleRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->string('type')->default('Vehicle');  // e.g. "Vehicle Request", "Fuel Request", "Maintenance Request", etc.
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
