<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A department is either academic (it belongs to a faculty, so leave goes
 * through the Dean) or administrative (it stands alone).
 */
class AddStructureToDepartmentsTable extends Migration
{
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('name');
            $table->string('type', 20)->default('administrative')->after('code');
            $table->unsignedBigInteger('faculty_id')->nullable()->index()->after('type');
        });
    }

    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropIndex(['faculty_id']);
            $table->dropColumn(['code', 'type', 'faculty_id']);
        });
    }
}
