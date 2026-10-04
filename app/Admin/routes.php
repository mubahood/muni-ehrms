<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');

    /*
    | Muni University EHRMS: attendance, leave and reports. Access to every path
    | is decided by App\Services\AccessPolicy (see AdminRoleMiddleware).
    */
    $router->get('me', 'StaffDashboardController@mine');
    $router->get('staff/{user}', 'StaffDashboardController@show')->where('user', '[0-9]+');

    $router->get('notifications', 'NotificationController@index');
    $router->get('notifications/summary', 'NotificationController@summary');
    $router->post('notifications/read-all', 'NotificationController@readAll');
    $router->get('notifications/{id}', 'NotificationController@open');

    $router->get('my-leave', 'MyLeaveController@index');
    $router->get('my-leave/apply', 'MyLeaveController@create');
    $router->post('my-leave', 'MyLeaveController@store');
    $router->get('my-leave/check', 'MyLeaveController@check');

    $router->get('leave/approvals', 'LeaveAdminController@approvals');
    $router->get('leave/all', 'LeaveAdminController@all');
    $router->get('leave/planning', 'LeaveAdminController@planning');
    $router->post('leave/planning', 'LeaveAdminController@savePlanning');
    $router->get('leave/record', 'LeaveAdminController@record');
    $router->post('leave/record', 'LeaveAdminController@storeRecord');
    $router->get('leave/{leave}', 'LeaveRequestController@show')->where('leave', '[0-9]+');
    $router->get('leave/{leave}/form.pdf', 'LeaveRequestController@form')->where('leave', '[0-9]+');
    $router->post('leave/{leave}/{action}', 'LeaveRequestController@act')
        ->where(['leave' => '[0-9]+', 'action' => 'approve|reject|withdraw|cancel|recall']);
    $router->get('leaves', fn () => redirect(admin_url('leave/all')));

    $router->resource('faculties', FacultyController::class)->except(['destroy']);
    $router->resource('public-holidays', PublicHolidayController::class);
    $router->get('settings', 'SettingsController@edit');
    $router->put('settings', 'SettingsController@update');
    $router->get('audit-log', 'AuditLogController@index');
    $router->get('demo-data', 'DemoDataController@index');
    $router->post('demo-data/logins', 'DemoDataController@logins');
    $router->post('demo-data/enter', 'DemoDataController@enter');
    $router->post('demo/leave', 'DemoDataController@leave');
    $router->post('demo-data/rebuild', 'DemoDataController@rebuild');
    $router->post('demo-data/purge', 'DemoDataController@purge');
    $router->delete('demo-data/accounts/{user}', 'DemoDataController@destroy')->where('user', '[0-9]+');

    $router->get('lookup/people', 'LookupController@people');
    $router->get('reports', 'ReportsController@index');
    $router->get('reports/{type}.pdf', 'ReportsController@pdf')->where('type', 'individual|summary|daily|leave');
    $router->get('reports/{type}.csv', 'ReportsController@csv')->where('type', 'individual|summary|daily|leave');
    $router->resource('users', UsersController::class);
    
    // Admin-only routes with permission-based access control
    $router->resource('departments', DepartmentController::class)->middleware('admin.permission:check,admin.departments');
    $router->get('system-configurations/{any?}', fn () => redirect(admin_url('settings')))->where('any', '.*');
    $router->resource('event-logs', EventLogController::class)->middleware('admin.permission:check,admin.event-logs');
    $router->get('event-logs-dashboard', 'EventLogController@dashboard')->name('event-logs.dashboard')->middleware('admin.permission:check,admin.event-logs');
    $router->resource('import-user-datas', ImportUserDataController::class)->middleware('admin.role');
    $router->resource('import-attendance-records', ImportAttendanceRecordController::class)->middleware('admin.role');
    
    // Regular user routes
    $router->resource('departmets', DepartmetController::class);
    $router->resource('vehicles', VehicleController::class);
    $router->resource('vehicle-requests', VehicleRequestController::class);
    $router->resource('materials-requests', VehicleRequestController::class);
    $router->resource('leave-requests', VehicleRequestController::class);
    $router->resource('all-requests', VehicleRequestController::class);
    $router->resource('archived-requests', VehicleRequestController::class);
    $router->resource('companies', CompanyController::class);
    $router->resource('exit-records', ExitRecordController::class);
    $router->resource('attendance-records', AttendanceRecordController::class);
    $router->get('general-reports/{id}/file', 'GeneralReportController@file')->where('id', '[0-9]+');
    $router->resource('general-reports', GeneralReportController::class);
});
