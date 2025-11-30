<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\MainController;
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


Route::get('import-employees', function (Request $r) {

    $employees = [
        ["name" => "KHALFAN HANAN MUTASIM", "phone" => "0771665405"],
        ["name" => "BRENDA NAKACWA FAITH", "phone" => "0707902006"],
        ["name" => "KATENDE KARIM", "phone" => "0759019192"],
        ["name" => "MUTAWE ABU BAKAR", "phone" => "0757769707"],
        ["name" => "MAKHUWA ABDUL WAHAB", "phone" => "0708454691"],
        ["name" => "NAKAMYA CLAIRE", "phone" => "0756224819"],
        ["name" => "NGONI ABU BAKAR SALIM", "phone" => "0752629977"],
        ["name" => "MUKABALISA HOPE", "phone" => "0782690090"],
        ["name" => "NAKAWEESA RAHMAH", "phone" => "0703197127"],
        ["name" => "NAKINTU JACKLINE", "phone" => "0753502945"],
        ["name" => "AINEOMUGISHA WALID", "phone" => "0703076464"],
        ["name" => "GWITABINGI LYDIA", "phone" => "0751813958"],
        ["name" => "MARYAMO SULEIMAN M", "phone" => "0740158977"],
        ["name" => "MULINDWA CALEB", "phone" => "0758948739"],
        ["name" => "MAHA MOHAMMED SALIH", "phone" => "0782112268"],
        ["name" => "NAFULA DAPHINE", "phone" => "0760346145"],
        ["name" => "MUSOKE MUHAMMED", "phone" => "0751644283"],
        ["name" => "NAKAYEMBA FAITH", "phone" => "0757821175"],
        ["name" => "NDAGIRE JACKIE", "phone" => "0709855563"],
        ["name" => "SSANSA MARVIN", "phone" => "0703395774"],
        ["name" => "KWIRINGIRA AMOS DAVID", "phone" => "0752561298"],
        ["name" => "MUGWIRE JACKSON", "phone" => "0705180599"],
        ["name" => "NYOMBI BRIAN", "phone" => "0704830199"],
        ["name" => "SENGO ALI", "phone" => "0759638324"],
        ["name" => "KIMBUGWE ELIJAH AVIAS", "phone" => "0753597601"],
        ["name" => "ALOWOOZA MARIAM N", "phone" => "0778970225"],
        ["name" => "KASAIJA ARAALI", "phone" => "0704572789"],
        ["name" => "NAGASHA PATRICIA", "phone" => "0757400481"],
        ["name" => "SSEKITTO ABDALLAH", "phone" => "0741061562"],
        ["name" => "FARDOWSA ABDI HUSSEIN", "phone" => "0750131429"],
        ["name" => "ZAKAIRE AHAMED M", "phone" => "0702038557"],
        ["name" => "ASIIMWE HAAWA", "phone" => "0778988252"],
        ["name" => "MWESIGWA BASIR", "phone" => "0703809923"],
        ["name" => "YASMIN SAID MOHAMUD", "phone" => "0702060308"],
        ["name" => "AISHA MOHAMMED", "phone" => "0705149918"],
        ["name" => "KASIRIVU HUSSEIN ALI", "phone" => "0702315060"],
        ["name" => "ABDINASIR YUSSUF ABDILLE", "phone" => "0780689838"],
        ["name" => "SUDI ABDI AHMED", "phone" => "0750955812"],
        ["name" => "ABDIRAHIM NOR WARSAME", "phone" => "0767818241"],
        ["name" => "NAMWANJE SHAMIM", "phone" => "—"],
    ];



    $id = 0;
    foreach ($employees as $key => $employee) {
        $id++;
        $user = User::find($id);
        if ($user == null) {
            $user = new User();
            $user->id = $id;
        }
        $user->name = $employee['name'];
        $user->username = $employee['name'];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $user->work_days = $days;
        $user->password = bcrypt('password'); // Default password
        $nameParts = explode(' ', $employee['name'], 2);
        $user->first_name = $nameParts[0];
        $user->last_name = isset($nameParts[1]) ? $nameParts[1] : '';
        $user->reg_date = now();
        $user->last_seen = now();
        $user->email = null;
        $user->approved = 1;
        $user->profile_photo = null;
        $user->user_type = 'employee';
        $user->sex = null;
        $user->reg_number = null;
        $user->country = null;
        $user->occupation = null;
        $user->profile_photo_large = null;
        $user->phone_number = $employee['phone'];
        $user->location_lat = null;
        $user->location_long = null;
        $user->facebook = null;
        $user->twitter = null;
        $user->whatsapp = null;
        $user->linkedin = null;
        $user->website = null;
        $user->other_link = null;
        $user->cv = null;
        $user->language = null;
        $user->about = null;
        $user->address = null;
        $user->created_at = now();
        $user->updated_at = now();
        $user->remember_token = null;
        $user->avatar = null;
        $user->campus_id = '1';
        $user->complete_profile = 0;
        $user->title = null;
        $user->dob = null;
        $user->intro = null;
        $user->is_mail_verified = 0;
        $user->mail_verification_token = null;
        $user->mail_verification_time = null;
        $user->is_mail_verification_code_sent = 0;
        $user->department_id = null;
        $user->company_id = 1;
        $user->change_password = 0;
        $user->has_changed_password = 0;
        $user->notify_account_created_by_email = 0;
        $user->position = null;
        $user->status = 'Active';
        $user->start_working_date = now();
        $user->hours = 0;
        $user->save();
        echo "User {$user->id} - {$user->name} imported successfully.<br>";
    }
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
});


Route::get('download-user-form-data', function (Request $request) {
    $activeUsers = User::where('status', 'Active')->get();
    $file_Nmae = 'Employee Form.xls';

    $header = [
        'ID',
        'Name',
        'Department',
        'Authority',
    ];

    $data = [];
    $i = 0;
    for ($i = 0; $i < 7; $i++) {
        $data[] = []; // Add empty rows for the first 8 rows
    }
    $data[] = $header; // Add header row
    $isFirstRow = true;
    foreach ($activeUsers as $user) {
        $isAdmin = '0';
        if ($user->isRole('admin')) {
            $isAdmin = 1;
        }
        if ($isFirstRow) {
            $isFirstRow = false;
            $isAdmin = 1; // Set first row as non-admin
        }
        $data[] = [
            $user->id,
            $user->name,
            1,
            $isAdmin,
        ];
    }
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($data, null, 'A1');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filePath = public_path($file_Nmae);
    $writer->save($filePath);
    return response()->download($filePath)->deleteFileAfterSend(true);
});
Route::get('print-general-reports', function (Request $request) {
    if (!$request->has('id')) {
        return "Error: Report ID is missing.";
    }

    $report = GeneralReport::findOrFail($request->id);

    // --- Start Data Processing ---
    $config = SystemConfiguration::first();
    $startDate = Carbon::parse($report->start_date);
    $endDate = Carbon::parse($report->end_date);
    $users = User::where('status', 'Active')->get();

    // 1. Executive Summary KPIs
    $totalPresentDays = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
        ->where('status', 'Present')->count();
    $totalAbsentDays = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
        ->where('status', 'Absent')->count();
    $totalLateIncidents = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
        ->where('is_late', 'Yes')->count();
    $totalHoursWorked = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
        ->where('status', 'Present')->sum('hours');
    $totalLeaveDays = Leave::where('start_date', '<=', $endDate)
        ->where('end_date', '>=', $startDate)->count();

    // 2. Trend Analysis by Day of Week
    $dayOfWeekData = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
        ->select(
            'day',
            DB::raw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count"),
            DB::raw("SUM(CASE WHEN is_late = 'Yes' THEN 1 ELSE 0 END) as late_count")
        )
        ->groupBy('day')
        ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
        ->get();

    // 3. Detailed Employee Records
    $employeeReports = [];
    foreach ($users as $user) {
        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get()->keyBy('attendance_date');

        $userSummary = [
            'name' => $user->name,
            'id' => $user->id,
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('is_late', 'Yes')->count(),
            'hours' => round($records->where('status', 'Present')->sum('hours'), 2),
            'log' => []
        ];

        $period = new \DatePeriod(
            new \DateTime($startDate->toDateString()),
            new \DateInterval('P1D'),
            new \DateTime($endDate->copy()->addDay()->toDateString())
        );

        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');
            $dayName = $date->format('l');
            $record = $records->get($currentDateStr);

            $userSummary['log'][$currentDateStr] = [
                'day' => $dayName,
                'status' => $record->status ?? ($user->isAvailableOnDay($currentDateStr) ? 'Missing' : 'Off Day'),
                'check_in' => $record->check_in_time ?? '-',
                'check_out' => $record->check_out_time ?? '-',
                'hours' => $record->hours ?? '-',
                'is_late' => $record->is_late ?? 'No',
            ];
        }

        $employeeReports[] = $userSummary;
    }

    $data = [
        'report' => $report,
        'config' => $config,
        'summary' => [
            'present' => $totalPresentDays,
            'absent' => $totalAbsentDays,
            'late' => $totalLateIncidents,
            'hours' => round($totalHoursWorked, 2),
            'leave' => $totalLeaveDays
        ],
        'dayOfWeekTrends' => $dayOfWeekData,
        'employeeReports' => $employeeReports,
    ];

    $pdf = App::make('dompdf.wrapper');
    $pdf->setPaper('a4', 'landscape');
    $pdf->loadHTML(view('reports.general-attendance', $data));

    return $pdf->stream('General-Attendance-Report-' . $report->id . '.pdf');
});

Route::get('send-new-password', function (Request $r) {
    //send new password to user
    $user = User::find($r->user_id);
    if ($user == null) {
        dd("User not found.");
    }
    try {
        $user->sendWelcomeMessage();
    } catch (\Exception $e) {
        return "Error sending new password: " . $e->getMessage();
    }
    dd("New password sent to user: " . $user->email);
});

Route::get('generate-attendance-records', function (Request $r) {
    $output = Utils::generate_attendance_records();

    return $output;
});



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
});

Route::get('auth/login', function () {
    return view('auth/login');
});


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
});


//endpoint for migration
Route::get('migrate', function () {
    //artisan migrate
    Artisan::call('migrate', ['--force' => true]);
    $output = Artisan::output();
    return nl2br($output);
});
