<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluateEndOfDayAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:evaluate-eod {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate and finalize attendance records at the end of the day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get date to evaluate (default to yesterday)
        $date = $this->argument('date') ?? Carbon::yesterday()->toDateString();
        
        $this->info("Evaluating attendance records for: {$date}");

        $attendanceRecords = AttendanceRecord::where('attendance_date', $date)->get();

        $stats = [
            'total' => $attendanceRecords->count(),
            'evaluated' => 0,
            'present' => 0,
            'half_day' => 0,
            'absent' => 0,
            'on_leave' => 0
        ];

        foreach ($attendanceRecords as $attendance) {
            try {
                $originalStatus = $attendance->status;
                
                // Re-evaluate status
                $newStatus = $this->evaluateFinalStatus($attendance, $date);
                
                if ($newStatus !== $originalStatus) {
                    $attendance->status = $newStatus;
                    
                    // Recalculate hours if needed
                    if ($newStatus == 'Present' && $attendance->check_in_time && $attendance->check_out_time) {
                        $attendance->hours = $this->calculateHours($attendance->check_in_time, $attendance->check_out_time);
                    } elseif ($newStatus == 'Half Day' && $attendance->check_in_time) {
                        // Use last available time (check_out or assume end of day)
                        $checkOutTime = $attendance->check_out_time ?? '17:00:00';
                        $attendance->hours = $this->calculateHours($attendance->check_in_time, $checkOutTime);
                    } else {
                        $attendance->hours = 0;
                    }
                    
                    $attendance->save();
                    
                    $this->line("Updated {$attendance->user->name}: {$originalStatus} → {$newStatus}");
                }
                
                $stats['evaluated']++;
                $stats[strtolower(str_replace(' ', '_', $attendance->status))]++;
                
            } catch (\Exception $e) {
                $this->error("Failed to evaluate attendance for user {$attendance->user_id}: {$e->getMessage()}");
                Log::error("End-of-day evaluation failed for attendance", [
                    'attendance_id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'date' => $date,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Display summary
        $this->info("\n=== End of Day Evaluation Summary ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records', $stats['total']],
                ['Evaluated', $stats['evaluated']],
                ['Present', $stats['present']],
                ['Half Day', $stats['half_day']],
                ['Absent', $stats['absent']],
                ['On Leave', $stats['on_leave']]
            ]
        );

        Log::info("End-of-day attendance evaluation completed", [
            'date' => $date,
            'stats' => $stats
        ]);

        return 0;
    }

    /**
     * Evaluate the final status for an attendance record
     *
     * @param AttendanceRecord $attendance
     * @param string $date
     * @return string
     */
    protected function evaluateFinalStatus(AttendanceRecord $attendance, string $date): string
    {
        // Check for active leave
        $hasLeave = Leave::where('user_id', $attendance->user_id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($hasLeave) {
            return 'On Leave';
        }

        // Evaluate based on clock-in and clock-out
        if ($attendance->check_in_time && $attendance->check_out_time) {
            return 'Present';
        }

        if ($attendance->check_in_time && !$attendance->check_out_time) {
            // Has clock-in but no clock-out = Half Day
            return 'Half Day';
        }

        // No clock-in = Absent
        return 'Absent';
    }

    /**
     * Calculate hours worked
     *
     * @param string $checkInTime
     * @param string $checkOutTime
     * @return float
     */
    protected function calculateHours(string $checkInTime, string $checkOutTime): float
    {
        $checkIn = Carbon::parse($checkInTime);
        $checkOut = Carbon::parse($checkOutTime);

        $totalMinutes = $checkOut->diffInMinutes($checkIn);
        $hours = $totalMinutes / 60;

        // Deduct break time if worked more than 4 hours
        if ($hours > 4) {
            $hours -= 1;
        }

        return round($hours, 2);
    }
}
