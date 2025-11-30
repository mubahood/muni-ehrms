<?php

use App\Models\Departmet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportUserDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_user_data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Departmet::class, 'department_id')
                ->nullable();
            $table->text('status')->nullable();
            $table->text('title')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('import_user_data');
    }
}
