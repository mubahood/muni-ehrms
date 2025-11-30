<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehiclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();


            // Basic identifiers
            $table->string('registration_number')->unique();  // e.g. "LV217", "AB-1234-CD"
            $table->string('vehicle_type')->nullable();       // e.g. "LV", "Bus", "Truck", etc.
            $table->string('brand')->nullable();              // e.g. "Toyota", "Ford"
            $table->string('model')->nullable();              // e.g. "Hilux"
            $table->string('color')->nullable();
            $table->string('year')->nullable();;


            // Maintenance or operational status
            $table->string('status')->default('Active');  // if the vehicle is currently in service
            $table->string('rent_status')->default('Available');  // if the vehicle is currently available for rent



        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicles');
    }
}
