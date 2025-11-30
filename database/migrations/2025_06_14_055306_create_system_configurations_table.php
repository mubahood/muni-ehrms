<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->text('company_phone')->nullable();
            $table->text('company_email')->nullable();
            $table->text('company_logo')->nullable();
            $table->date('start_date')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_configurations');
    }
}
