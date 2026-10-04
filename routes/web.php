<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EventLogController;
use App\Http\Controllers\GeneralReportPrintController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\ReportGenerationController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\AttendanceRecord;
use App\Models\GeneralReport;
use App\Models\ImportAttendanceRecord;
use App\Models\ImportUserData;
use App\Models\Leave;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Models\Utils;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Dflydev\DotAccessData\Util;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Sabberworm\CSS\Property\Import;

// Event Log Processing Routes
Route::get('process-event-logs', [EventLogController::class, 'processEventLogs'])->middleware('admin.auth');
Route::get('event-logs/statistics', [EventLogController::class, 'statistics'])->middleware('admin.auth');
Route::get('event-logs/process-page', [EventLogController::class, 'processPage'])->middleware('admin.auth');

// Report Generation API Routes
Route::prefix('api/reports')->middleware('admin.auth')->group(function () {
    Route::post('generate', [ReportGenerationController::class, 'generateGeneralReport']);
    Route::get('download/{id}', [ReportGenerationController::class, 'downloadReport']);
    Route::get('statistics/{id}', [ReportGenerationController::class, 'getStatistics']);
    Route::post('regenerate/{id}', [ReportGenerationController::class, 'regenerateReport']);
});

Route::get('do-import-attendance-records', function (Request $r) {
    /*  $allUsers = User::all();
    if ($allUsers->count() == 0) {
        dd("No users found in the system.");
    }
    $i = 0;
    foreach ($allUsers as $user) {

        //id ande name
        echo $user->id . " - " . $user->name . " : hours: " . $user->hours . "<br>";
    }
    die("Attendance records generated for all users for the current month.");
    //send new password to user */
    $data = ImportAttendanceRecord::find($r->id);
    if ($data == null) {
        dd("Data not found.");
    }

    $config = SystemConfiguration::where([])->first();
    if ($config == null) {
        throw new \Exception("System configuration not found.");
    }
    $ImportAttendanceRecord = $data;
    $file_path = public_path('storage/' . $data->file_path);
    //check if file exists
    if (!file_exists($file_path)) {
        dd("File not found at path: " . $file_path);
    }
    //load this excel file All Report.xls
    $excel = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
    $sheet = $excel->getSheet(2); // Tab 3 (index starts at 0)
    $rows = $sheet->toArray();
    $header = array_shift($rows); // Get the first row as header
    $dateRow = array_shift($rows); // Get the second row as date row

    if (!isset($dateRow[0]) || !isset($dateRow[2])) {
        dd("Invalid date row format. Expected at least 2 columns.");
    }

    $date_ranges = $dateRow[2]; // Assuming the date range is in the third column
    if (strpos($date_ranges, ' ~ ') === false) {
        dd("Invalid date range format. Expected 'start_date - end_date'.");
    }
    $date_parts = explode(' ~ ', $date_ranges);
    if (count($date_parts) !== 2) {
        dd("Invalid date range format. Expected 'start_date - end_date'.");
    }
    $min_date = trim($date_parts[0]);
    $max_date = trim($date_parts[1]);
    if (!strtotime($min_date) || !strtotime($max_date)) {
        dd("Invalid date format in date range: " . $date_ranges);
    }
    $min_date = date('Y-m-d', strtotime($min_date));
    $max_date = date('Y-m-d', strtotime($max_date));
    if ($min_date > $max_date) {
        dd("Invalid date range: start date is after end date.");
    }
    // Convert date range to array of dates
    $start_date = Carbon::parse($min_date);
    $end_date = Carbon::parse($max_date);

    //both must be in same month
    if ($start_date->month != $end_date->month || $start_date->year != $end_date->year) {
        dd("Invalid date range: start date and end date must be in the same month.");
    }
    $month = $start_date->month;
    $year = $start_date->year;

    $data = [];
    $recs = [];
    $originalRows = $rows; // Keep original rows for debugging
    $today = Carbon::now();
    $new_import_count = 0;
    Utils::generate_attendance_records();
    foreach ($rows as $key  => $row) {
        if (count($row) === count($header)) {
            $data[] = array_combine($header, $row);
        }
        $user = null;
        //check if is user_row
        if (
            isset($row['0']) &&
            isset($row['2']) &&
            isset($row['9'])
        ) {

            if (
                $row['0'] == 'ID' &&
                $row['9'] == 'Name'
            ) {
                $user_id = $row['2'];
                if ($user_id != null) {
                    $user = User::find($user_id);
                }
            }
        }
        if ($user == null) {
            continue;
        }
        if (!isset($rows[$key + 1])) {
            continue;
        }
        if (!isset($rows[$key + 2])) {
            continue;
        }
        if (!isset($rows[$key + 3])) {
            continue;
        }

        $dates = $rows[$key + 1];
        $days = $rows[$key + 2];
        $times = $rows[$key + 3];


        if ($new_import_count == 0) {
            echo "<style>
            table.attendance-table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 20px;
                font-family: Arial, sans-serif;
            }
            table.attendance-table th, table.attendance-table td {
                border: 1px solid #ccc;
                padding: 8px 12px;
                text-align: left;
            }
            table.attendance-table th {
                background: #f5f5f5;
                color: #333;
            }
            table.attendance-table tr:nth-child(even) {
                background: #fafafa;
            }
            </style>";
            echo "<table class='attendance-table'>";
            echo "<thead><tr>
            <th>#</th>
            <th>User</th>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Status</th>
            <th>Hours</th>
            </tr></thead>
            <tbody>";
        }
        $new_import_count++;
        //loop through days
        foreach ($dates as $date_key => $date) {
            if (trim($date) == '') {
                //dd("Invalid date found in row: " . ($key + 1) . ", column: " . ($date_key + 1));
                continue;
            }
            $date = trim($date);
            $date_val = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($date, 2, '0', STR_PAD_LEFT);
            $date_value = Carbon::parse($date_val)->toDateString();

            //check if is valid date
            if (!strtotime($date_value)) {
                //dd("Invalid date format: " . $date_value . " in row: " . ($key + 1) . ", column: " . ($date_key + 1));
                continue; // Skip invalid dates
            }
            //check future date
            if (Carbon::parse($date_value)->isFuture()) {
                //ECH("Future date found: " . $date_value . " in row: " . ($key + 1) . ", column: " . ($date_key + 1));
                continue; // Skip future dates
            }




            //attendance record
            $attendanceRecord = \App\Models\AttendanceRecord::where([
                'user_id' => $user->id,
                'attendance_date' => $date_value
            ])->first();
            if ($attendanceRecord == null) {
                continue; // Skip if no attendance record found
            }
            //$attendanceRecord->is_imported is 'Yes' if already imported
            if ($attendanceRecord->is_imported == 'Yes' && $attendanceRecord->status == 'Present') {
                echo "<tr style='background-color: #ffeeba;'><td>{$new_import_count}</td><td>{$user->name}</td><td>{$date_value}</td><td>{$attendanceRecord->check_in_time}</td><td>{$attendanceRecord->check_out_time}</td><td>Already Imported</td><td>{$attendanceRecord->hours}</td></tr>";
                continue; // Skip if already imported
            }



            $attendanceRecord->check_in_time = null;
            $attendanceRecord->check_out_time = null;
            $attendanceRecord->has_error = 'No'; // Assuming no error for valid records
            $attendanceRecord->error_message = ''; // Assuming no error for valid records
            $attendanceRecord->attendance_date = $date_value;
            $attendanceRecord->is_imported = 'Yes'; // Mark as imported
            $attendanceRecord->status = 'Absent'; // Assuming status is 'Absent' for valid records
            $attendanceRecord->import_record_id = $ImportAttendanceRecord->id; // Link to the import record
            $attendanceRecord->is_late = 'No';
            $attendanceRecord->hours = 0; // Set hours worked to 0 for absent records

            if (!isset($times[$date_key])) {
                $attendanceRecord->save(); // Save the attendance record
                echo "<tr style='background-color: #f8d7da;'><td>{$new_import_count}</td><td>{$user->name}</td><td>{$date_value}</td><td>Not Found</td><td>Not Found</td><td>Absent</td><td>0</td></tr>";
                continue; // Skip if no time record found
            }

            $time = $times[$date_key];
            $min_time = null;
            $max_time = null;

            if ($time != null && strlen($time) > 3) {
                $day_times = preg_split("/\r\n|\n|\r/", $time);
                foreach ($day_times as $t) {
                    $t = trim($t);
                    if (trim($t) == '') {
                        continue; // Skip empty times
                    }
                    if (strlen($t) < 3) {
                        continue; // Skip times that are too short
                    }
                    $time_obj = Carbon::createFromFormat('H:i', $t);
                    if ($min_time == null) {
                        $min_time = $t;
                    }
                    if ($max_time == null) {
                        $max_time = $t;
                    }

                    $ob_1 = Carbon::createFromFormat('H:i', $min_time);
                    $ob_2 = Carbon::createFromFormat('H:i', $max_time);
                    if ($time_obj->lessThan($ob_1)) {
                        $min_time = $t; // Update min_time if current time is less
                    }
                    if ($time_obj->greaterThan($ob_2)) {
                        $max_time = $t; // Update max_time if current time is greater
                    }
                }
            }
            //if min_time and max_time are still null, skip this date
            if ($min_time == null || $max_time == null) {
                $attendanceRecord->save(); // Save the attendance record
                echo "<tr style='background-color: #f8d7da;'><td>{$new_import_count}</td><td>{$user->name}</td><td>{$date_value}</td><td>Not Found</td><td>Not Found</td><td>Absent</td><td>0</td></tr>";
                continue; // Skip if no valid time found
            }

            $attendanceRecord->check_in_time = $min_time;
            $attendanceRecord->check_out_time = $max_time;
            $attendanceRecord->has_error = 'No'; // Assuming no error for valid records
            $attendanceRecord->error_message = ''; // Assuming no error for valid records
            $attendanceRecord->attendance_date = $date_value;
            $attendanceRecord->is_imported = 'Yes'; // Mark as imported
            $attendanceRecord->status = 'Present'; // Assuming status is 'Present' for valid records
            $attendanceRecord->import_record_id = $ImportAttendanceRecord->id; // Link to the import record

            $check_in_time_date_and = Carbon::createFromFormat('Y-m-d H:i', $date_value . ' ' . $min_time);

            $late_time =  $config->late_time;

            if ($attendanceRecord->user != null) {
                if ($attendanceRecord->user->title != null) {
                    if (strlen($attendanceRecord->user->title) > 5) {
                        $late_time = $attendanceRecord->user->title; // Use user's title as late time if set
                    }
                }
            }

            $check_in_late_date_and_time = Carbon::createFromFormat('Y-m-d H:i:s', $date_value . ' ' . $late_time);

            $attendanceRecord->is_late = 'No'; // Mark as imported
            if ($check_in_time_date_and->greaterThan($check_in_late_date_and_time)) {
                $attendanceRecord->is_late = 'Yes'; // Mark as late if check-in time is after late time
            }
            $_start_time = Carbon::createFromFormat('Y-m-d H:i', $date_value . ' ' . $min_time);
            $_end_time = Carbon::createFromFormat('Y-m-d H:i', $date_value . ' ' . $max_time);
            $hours = $_end_time->diffInHours($_start_time);
            $attendanceRecord->hours = $hours; // Set hours worked
            $attendanceRecord->save(); // Save the attendance record
            //echo summary with a new line

            echo "<tr><td>{$new_import_count}</td><td>{$user->name}</td><td>{$date_value}</td><td>{$min_time}</td><td>{$max_time}</td><td>Present</td><td>{$hours}</td></tr>";
        }
    }
    if ($new_import_count > 0) {
        echo "</tbody></table>";
        echo "<p>Imported {$new_import_count} attendance records.</p>";
    } else {
        echo "<p>No new attendance records imported.</p>";
    }
})->middleware('admin.auth');



// General Reports Routes - Using Dedicated Controller
Route::get('print-general-reports', [GeneralReportPrintController::class, 'generate'])->name('general-reports.generate')->middleware('admin.auth');
Route::get('print-general-reports/print', [GeneralReportPrintController::class, 'view'])->name('general-reports.view')->middleware('admin.auth');





Route::get('import-user-data', function (Request $r) {
    $rec = ImportUserData::find($r->id);
    if ($rec == null) {
        dd("Record not found.");
    }
    $path = public_path('storage/' . $rec->title);

    //check 
    if (!file_exists($path)) {
        dd("File not found at path: " . $path);
    }

    //csv
    $csvData = file_get_contents($path);
    if ($csvData === false) {
        dd("Failed to read the file at path: " . $path);
    }
    $lines = explode("\n", $csvData);
    $header = str_getcsv(array_shift($lines));
    $data = [];
    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) === count($header)) {
            $data[] = array_combine($header, $row);
        }
    }
    // Process the data as needed
    /* 
    1 => array:3 [▼
    "name" => "Mohindo Jane"
    "gender" => "Female"
    "email" => "mail2@gmail.com"
    ]
     */

    $successCount = 0;
    $errorCount = 0;
    $count = 0;
    $totalCount = count($data);
    foreach ($data as $row) {
        echo "<hr>";
        $count++;
        if (!isset($row['name']) || !isset($row['email'])) {
            $errorCount++;
            echo "<span style='color: red;'>[$count/$totalCount] Invalid row data. Missing 'name' or 'email'.</span><br>";
            continue; // Skip to the next row if data is invalid
        }
        // Process each row
        // For example, you can print the name and email
        $existingUser = User::where('email', $row['email'])->first();
        if ($existingUser == null) {
            $existingUser = User::where('username', $row['email'])->first();
        }
        if ($existingUser != null) {
            $errorCount++;
            echo "<span style='color: red;'>
                [$count/$totalCount] User with email <strong>{$row['email']}</strong> already exists.
                Name: <strong>{$row['name']}</strong>, Gender: <strong>{$row['gender']}</strong>
            </span><br>";
            continue; // Skip to the next row if user already exists
        }


        $user = new User();
        $user->name = $row['name'];
        $user->email = $row['email'];
        $user->sex = $row['gender'] ?? null; // Use
        $user->username = $row['email'];
        $user->department_id = $rec->department_id;
        $user->company_id = 1;
        $user->password = password_hash($user->email, PASSWORD_BCRYPT);

        $name_parts = explode(' ', $user->name);
        if (count($name_parts) > 1) {
            $user->first_name = $name_parts[0];
            $user->last_name = implode(' ', array_slice($name_parts, 1));
        } else {
            $user->first_name = $user->name;
            $user->last_name = null;
        }

        try {
            $user->save();
            $successCount++;
            echo "<span style='color: green;'>[$count/$totalCount] User <strong>{$user->name}</strong> created successfully.</span><br>";
        } catch (\Exception $e) {
            $errorCount++;
            echo "<span style='color: red;'>[$count/$totalCount] Error creating user: {$e->getMessage()}</span><br>";
        }

        //assign role 2
        $sql = "INSERT INTO `admin_role_users` (`user_id`, `role_id`) VALUES ({$user->id}, 2)";
        try {
            DB::insert($sql);
        } catch (\Exception $e) {
            echo "<span style='color: red;'>[$count/$totalCount] Error assigning role to user: {$e->getMessage()}</span><br>";
        }
    }
    echo "<hr>";
    echo "<h3>Import Summary</h3>";
    echo "<p>Total Rows: $totalCount</p>";
    echo "<p>Successful Imports: $successCount</p>";
    echo "<p>Failed Imports: $errorCount</p>";
    echo "<p>Check the logs for more details.</p>";
    return;
})->middleware('admin.auth');

Route::get('auth/login', function () {
    return view('auth/login', [
        'demoAccounts' => \App\Admin\Controllers\AuthController::demoAccounts(),
        'demoPassword' => config('demo.password'),
    ]);
});

Route::post('auth/login', 'App\Admin\Controllers\AuthController@postLogin');


Route::get('print-gatepass', function (Request $request) {
    $item = VehicleRequest::find($request->gatepass_id);
    if ($item == null) {
        die("Item not found.");
    }
    $pdf = App::make('dompdf.wrapper');

    $file = 'print-materials';

    if ($item->type == 'Vehicle') {
        $file = 'print-gatepass';
    } else {
        $file = 'print-materials';
    }

    $pdf->loadHTML(view('print/' . $file, [
        'item' => $item
    ]));
    return $pdf->stream();
})->middleware('admin.auth');


