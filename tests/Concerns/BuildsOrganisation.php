<?php

namespace Tests\Concerns;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\PublicHoliday;
use App\Models\User;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Small factories for the tests: people with roles, departments, faculties,
 * clock-ins and holidays.
 */
trait BuildsOrganisation
{
    protected function makeUser(array $attributes = [], array $roles = ['employee']): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'username' => 't.' . Str::lower(Str::random(8)),
            'name' => 'Test ' . Str::title(Str::random(6)),
            'password' => bcrypt('Secret@2026'),
            'status' => 'Active',
            'campus_id' => 1,
            'employee_no' => 'T' . random_int(10000, 99999),
            'start_working_date' => '2026-01-01',
            'work_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        ], $attributes));
        $user->save();
        $user->roles()->sync(Role::whereIn('slug', $roles)->pluck('id'));

        return $user->fresh();
    }

    protected function makeFaculty(array $attributes = []): Faculty
    {
        return Faculty::create(array_merge([
            'name' => 'Faculty of ' . Str::title(Str::random(6)),
            'code' => 'F' . Str::upper(Str::random(4)),
        ], $attributes));
    }

    protected function makeDepartment(array $attributes = []): Department
    {
        return Department::create(array_merge([
            'name' => 'Department of ' . Str::title(Str::random(6)),
            'code' => 'D' . Str::upper(Str::random(4)),
            'type' => Department::ADMINISTRATIVE,
            'is_active' => true,
        ], $attributes));
    }

    /** Record clock-ins for a person as the Hikvision bridge would. */
    protected function clockIn(User $user, string ...$datetimes): void
    {
        foreach ($datetimes as $datetime) {
            DB::table('event_logs')->insert([
                'event_time' => $datetime,
                'employee_no' => $user->employee_no,
                'employee_name' => $user->name,
                'user_id' => $user->id,
                'source' => 'webhook',
                'process_status' => 'processed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function holiday(string $date, string $name = 'Test holiday'): PublicHoliday
    {
        return PublicHoliday::create(['date' => $date, 'name' => $name]);
    }

    protected function record(User $user, string $date): ?object
    {
        return DB::table('attendance_records')->where('user_id', $user->id)->where('attendance_date', $date)->first();
    }
}
