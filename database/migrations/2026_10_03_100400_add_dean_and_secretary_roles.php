<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The leave application form is signed by a Faculty Dean (academic staff) and
 * decided by the University Secretary. Both need their own role.
 */
class AddDeanAndSecretaryRoles extends Migration
{
    private const ROLES = [
        'dean' => 'Faculty Dean',
        'us' => 'University Secretary',
    ];

    public function up()
    {
        foreach (self::ROLES as $slug => $name) {
            if (!DB::table('admin_roles')->where('slug', $slug)->exists()) {
                DB::table('admin_roles')->insert([
                    'slug' => $slug, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        $ids = DB::table('admin_roles')->whereIn('slug', array_keys(self::ROLES))->pluck('id');
        DB::table('admin_role_users')->whereIn('role_id', $ids)->delete();
        DB::table('admin_roles')->whereIn('id', $ids)->delete();
    }
}
