<?php

/**
 * Laravel-admin - admin builder based on Laravel.
 * @author z-song <https://github.com/z-song>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 * Encore\Admin\Form::forget(['map', 'editor']);
 *
 * Or extend custom form field:
 * Encore\Admin\Form::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 *
 */


use App\Models\Utils;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\Auth;
use App\Admin\Extensions\Nav\Shortcut;
use App\Admin\Extensions\Nav\Dropdown;
use App\Models\AdminRoleUser;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Carbon\Carbon;
use Encore\Admin\Form;
use Illuminate\Support\Facades\DB;
use App\Services\AccessControlService;
use App\Admin\Extensions\PermissionChecker;

$x = 0;
$max = 300;
$user_ids = User::all()->pluck('id')->toArray();
$faker = Faker\Factory::create();
$vehicles_ids = Vehicle::all()->pluck('id')->toArray();

// Inject our custom CSS files into the header
Admin::css(asset('css/muni-ehrms.css'));      // Master stylesheet
Admin::css(asset('css/custom-dashboard.css')); // Dashboard specific

Admin::css(url('/assets/css/bootstrap.css'));
Admin::css('/assets/css/styles.css');

//one record for AttendanceRecord for today
$today = Carbon::now()->format('Y-m-d');
$attendanceRecord = AttendanceRecord::whereDate('created_at', $today) 
    ->first();
if (!$attendanceRecord) { 
}
Utils::generate_attendance_records();

// Override Laravel Admin's menu filtering to respect permissions
Admin::booting(function () {
    // Initialize our custom permission checker
    PermissionChecker::intercept();
    
    // Filter menu items based on user permissions
    Admin::menu()->filter(function ($item) {
        // If no permission is set, allow access (backward compatibility)
        if (empty($item['permission'])) {
            return true;
        }
        
        return AccessControlService::hasAccess($item['permission']);
    });
});

// Extend the user form to include department assignment
Admin::booting(function () {
    Form::composing('admin.auth.users', function ($form) {
        $form->select('department_id', 'Department')
            ->options(Department::where('is_active', true)
                ->pluck('name', 'id'))
            ->help('Assign this user to a department');
    });
});
