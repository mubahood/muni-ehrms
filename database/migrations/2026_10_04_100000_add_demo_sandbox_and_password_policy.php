<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The demo sandbox and the password policy.
 *
 *  - users / departments / faculties.is_demo: rows of the demonstration
 *    university. Demo accounts only ever see demo rows and real people never
 *    see them (App\Services\Scope); the admin removes them all in one go.
 *  - users.must_change_password: the person has to choose a new password
 *    before using the system (set for accounts found with weak passwords).
 *  - system_configurations.demo_logins: show the demo sign-in panel on the
 *    login page (switched by the System Administrator under Demo data).
 */
class AddDemoSandboxAndPasswordPolicy extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index();
            }
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false);
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
        });
        foreach (['departments', 'faculties'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index();
                }
            });
        }
        Schema::table('system_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('system_configurations', 'demo_logins')) {
                $table->boolean('demo_logins')->default(false);
            }
        });
    }

    public function down()
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_demo', 'must_change_password', 'password_changed_at']));
        Schema::table('departments', fn (Blueprint $t) => $t->dropColumn('is_demo'));
        Schema::table('faculties', fn (Blueprint $t) => $t->dropColumn('is_demo'));
        Schema::table('system_configurations', fn (Blueprint $t) => $t->dropColumn('demo_logins'));
    }
}
