<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('type')->nullable();  // e.g. "Fuel", "Oil", "Tire", "Battery", etc.
            $table->text('description')->nullable();  // e.g. "10 liters of diesel", "1 tire for a Toyota Corolla", "1 battery for a Toyota Corolla", etc.
            $table->text('unit')->nullable();  // e.g. "liters", "tires", "batteries", etc.
            $table->integer('quantity')->nullable();  // e.g. 10, 1, 1, etc.
            $table->foreignIdFor(\App\Models\VehicleRequest::class)->nullable();  // e.g. 1, 2, 3, etc.
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('material_items');
    }
}
