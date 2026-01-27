<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EventLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'event_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_serial',
        'device_serial',
        'event_serial_no',
        'event_index_code',
        'major',
        'minor',
        'event_type',
        'event_time',
        'event_time_raw',
        'employee_no',
        'employee_name',
        'card_no',
        'card_type',
        'verify_mode',
        'mask_detected',
        'temperature',
        'door_no',
        'channel_no',
        'device_ip',
        'device_name',
        'picture_url',
        'has_picture',
        'raw_data',
        'process_status',
        'process_error',
        'processed_at',
        'user_id',
        'attendance_record_id',
        'source',
        'batch_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'raw_data' => 'array',
        'event_time' => 'datetime',
        'processed_at' => 'datetime',
        'mask_detected' => 'boolean',
        'has_picture' => 'boolean',
        'temperature' => 'decimal:2',
        'major' => 'integer',
        'minor' => 'integer',
        'event_serial_no' => 'integer',
    ];

    /**
     * Process status constants
     */
    const STATUS_UNPROCESSED = 'unprocessed';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-set event type if not provided
            if (empty($model->event_type) && !empty($model->major)) {
                $model->event_type = self::getEventTypeName($model->major, $model->minor);
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the user (employee) associated with this event
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the attendance record linked to this event
     */
    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope for unprocessed events
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('process_status', self::STATUS_UNPROCESSED);
    }

    /**
     * Scope for processed events
     */
    public function scopeProcessed($query)
    {
        return $query->where('process_status', self::STATUS_PROCESSED);
    }

    /**
     * Scope for failed events
     */
    public function scopeFailed($query)
    {
        return $query->where('process_status', self::STATUS_FAILED);
    }

    /**
     * Scope for events by employee number
     */
    public function scopeByEmployee($query, $employeeNo)
    {
        return $query->where('employee_no', $employeeNo);
    }

    /**
     * Scope for events on a specific date
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('event_time', $date);
    }

    /**
     * Scope for events in date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('event_time', [$start, $end]);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted event time
     */
    public function getFormattedEventTimeAttribute()
    {
        return $this->event_time ? $this->event_time->format('Y-m-d H:i:s') : null;
    }

    /**
     * Get event time as time only
     */
    public function getEventTimeOnlyAttribute()
    {
        return $this->event_time ? $this->event_time->format('H:i:s') : null;
    }

    /**
     * Get event date only
     */
    public function getEventDateAttribute()
    {
        return $this->event_time ? $this->event_time->format('Y-m-d') : null;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match ($this->process_status) {
            self::STATUS_UNPROCESSED => 'warning',
            self::STATUS_PROCESSED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_SKIPPED => 'default',
            default => 'default',
        };
    }

    /**
     * Get verify mode display name
     */
    public function getVerifyModeDisplayAttribute()
    {
        $modes = [
            'face' => 'Face Recognition',
            'card' => 'Card',
            'fingerprint' => 'Fingerprint',
            'password' => 'Password',
            'qrCode' => 'QR Code',
            'faceAndCard' => 'Face + Card',
            'faceAndFingerprint' => 'Face + Fingerprint',
        ];

        return $modes[$this->verify_mode] ?? $this->verify_mode ?? 'Unknown';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Mark event as processed
     */
    public function markAsProcessed($attendanceRecordId = null, $userId = null)
    {
        $this->process_status = self::STATUS_PROCESSED;
        $this->processed_at = now();
        $this->process_error = null;
        
        if ($attendanceRecordId) {
            $this->attendance_record_id = $attendanceRecordId;
        }
        
        if ($userId) {
            $this->user_id = $userId;
        }
        
        return $this->save();
    }

    /**
     * Mark event as failed
     */
    public function markAsFailed($errorMessage)
    {
        $this->process_status = self::STATUS_FAILED;
        $this->processed_at = now();
        $this->process_error = $errorMessage;
        
        return $this->save();
    }

    /**
     * Mark event as skipped
     */
    public function markAsSkipped($reason = null)
    {
        $this->process_status = self::STATUS_SKIPPED;
        $this->processed_at = now();
        $this->process_error = $reason;
        
        return $this->save();
    }

    /**
     * Link user by employee number
     */
    public function linkUser()
    {
        if (empty($this->employee_no)) {
            return false;
        }

        // Try to find user by employee number (new column), then fallback to id or username
        $user = User::where('employee_no', $this->employee_no)
            ->orWhere('id', $this->employee_no)
            ->orWhere('username', $this->employee_no)
            ->first();

        if ($user) {
            $this->user_id = $user->id;
            $this->save();
            return $user;
        }

        return false;
    }

    /**
     * Get event type name from major/minor codes
     */
    public static function getEventTypeName($major, $minor = null)
    {
        $majorTypes = [
            1 => 'Alarm',
            2 => 'Exception',
            3 => 'Operation',
            5 => 'Access Event',
        ];

        $accessMinorTypes = [
            1 => 'Legal Card Pass',
            20 => 'Legal Fingerprint Pass',
            75 => 'Face Recognition Pass',
            76 => 'Face Recognition Fail',
            77 => 'Face Anti-spoofing Fail',
            200 => 'Remote Open Door',
        ];

        $majorName = $majorTypes[$major] ?? "Type {$major}";
        
        if ($major == 5 && $minor !== null) {
            $minorName = $accessMinorTypes[$minor] ?? null;
            if ($minorName) {
                return "{$majorName}: {$minorName}";
            }
        }

        return $majorName;
    }

    /**
     * Create EventLog from Hikvision raw event data
     */
    public static function createFromHikvisionEvent(array $eventData, string $deviceSerial = null, string $source = 'webhook', string $batchId = null)
    {
        // Parse event time
        $eventTime = null;
        $eventTimeRaw = $eventData['time'] ?? null;
        
        if ($eventTimeRaw) {
            try {
                // Remove timezone info and parse
                $cleanTime = preg_replace('/[+-]\d{2}:\d{2}$/', '', $eventTimeRaw);
                $eventTime = Carbon::parse($cleanTime);
            } catch (\Exception $e) {
                $eventTime = null;
            }
        }

        // Build the event log data
        $data = [
            'device_serial' => $deviceSerial ?? ($eventData['deviceSerial'] ?? null),
            'event_serial_no' => $eventData['serialNo'] ?? null,
            'event_index_code' => $eventData['eventIndexCode'] ?? null,
            'major' => $eventData['major'] ?? null,
            'minor' => $eventData['minor'] ?? null,
            'event_time' => $eventTime,
            'event_time_raw' => $eventTimeRaw,
            'employee_no' => $eventData['employeeNoString'] ?? ($eventData['employeeNo'] ?? null),
            'employee_name' => $eventData['name'] ?? null,
            'card_no' => $eventData['cardNo'] ?? null,
            'card_type' => isset($eventData['cardType']) ? (string)$eventData['cardType'] : null,
            'verify_mode' => self::parseVerifyMode($eventData),
            'mask_detected' => isset($eventData['mask']) ? ($eventData['mask'] == 1 || $eventData['mask'] === true) : null,
            'temperature' => $eventData['currTemperature'] ?? ($eventData['temperature'] ?? null),
            'door_no' => $eventData['doorNo'] ?? null,
            'channel_no' => $eventData['channelNo'] ?? null,
            'device_ip' => $eventData['deviceIP'] ?? ($eventData['ipAddress'] ?? null),
            'device_name' => $eventData['deviceName'] ?? null,
            'picture_url' => $eventData['pictureURL'] ?? ($eventData['picturesURL'] ?? null),
            'has_picture' => !empty($eventData['pictureURL']) || !empty($eventData['picturesURL']),
            'raw_data' => $eventData,
            'process_status' => self::STATUS_UNPROCESSED,
            'source' => $source,
            'batch_id' => $batchId,
        ];

        return self::create($data);
    }

    /**
     * Parse verify mode from event data
     */
    protected static function parseVerifyMode(array $eventData)
    {
        // Check various fields for verification mode
        if (isset($eventData['currentVerifyMode'])) {
            return $eventData['currentVerifyMode'];
        }
        
        // Infer from minor event type
        $minor = $eventData['minor'] ?? null;
        
        return match ($minor) {
            1 => 'card',
            20 => 'fingerprint',
            75, 76, 77 => 'face',
            default => null,
        };
    }

    /**
     * Check if event already exists (for duplicate prevention)
     */
    public static function isDuplicate($deviceSerial, $eventSerialNo)
    {
        if (empty($deviceSerial) || empty($eventSerialNo)) {
            return false;
        }

        return self::where('device_serial', $deviceSerial)
            ->where('event_serial_no', $eventSerialNo)
            ->exists();
    }
}
