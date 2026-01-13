<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddEventLogsMenuItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get the maximum order number
        $maxOrder = DB::table('admin_menu')->max('order') ?? 0;
        
        // Insert the Event Logs menu item
        DB::table('admin_menu')->insert([
            'parent_id' => 0,
            'order' => $maxOrder + 1,
            'title' => 'Event Logs',
            'icon' => 'fa-clock-o',
            'uri' => 'event-logs',
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('admin_menu')->where('uri', 'event-logs')->delete();
    }
}
