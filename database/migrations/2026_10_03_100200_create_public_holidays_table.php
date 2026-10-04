<?php

use App\Support\UgandaHolidays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public holidays are never working days: nobody is absent or late on them.
 * The statutory holidays for this year and next are filled in; Eid days depend
 * on the moon and are added by Human Resource once gazetted.
 */
class CreatePublicHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        $year = (int) now()->format('Y');
        $rows = [];
        foreach ([$year, $year + 1] as $y) {
            foreach (UgandaHolidays::forYear($y) as $date => $name) {
                $rows[] = ['date' => $date, 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('public_holidays')->insert($rows);
    }

    public function down()
    {
        Schema::dropIfExists('public_holidays');
    }
}
