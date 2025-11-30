<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('departmets', function (Blueprint $table) {
            $table->id();
            $table->timestamps(); 
            $table->text('name')->nullable();
            $table->text('code')->nullable();
            $table->text('description')->nullable();
            $table->foreignIdFor(User::class, 'head_of_department_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('departmets');
    }
}
