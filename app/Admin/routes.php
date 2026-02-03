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
    $router->get('/calendar', 'HomeController@calendar')->name('calendar');
    $router->resource('users', UsersController::class);
    
    // Admin-only routes with permission-based access control
    $router->resource('departments', DepartmentController::class)->middleware('admin.permission:check,admin.departments');
    $router->resource('system-configurations', SystemConfigurationController::class)->middleware('admin.permission:check,admin.system-configurations');
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
    $router->resource('leaves', LeaveController::class);
    $router->resource('attendance-records', AttendanceRecordController::class);
    $router->resource('general-reports', GeneralReportController::class);
});
