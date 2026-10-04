<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class GeneralReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'start_date',
        'end_date',
        'file_path',
        'is_generated',
        'status',
        'report_type',
        'user_id',
        'target_user_id',
        'target_department_id',
        'description',
        'total_employees',
        'total_records',
        'generation_time',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'generation_time' => 'float',
        'total_employees' => 'integer',
        'total_records' => 'integer',
    ];

    /**
     * Get the user who created this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target user for user-specific reports.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the target department for department-specific reports.
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    /**
     * Scope for completed reports.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_generated', 'Yes')
            ->where('status', 'completed');
    }

    /**
     * Scope for pending reports.
     */
    public function scopePending($query)
    {
        return $query->where('is_generated', 'No')
            ->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope for failed reports.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for date range filtering.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate);
    }

    /**
     * Scope for recent reports.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope for general reports (all employees).
     */
    public function scopeGeneral($query)
    {
        return $query->where('report_type', 'general');
    }

    /**
     * Scope for user-specific reports.
     */
    public function scopeUserSpecific($query)
    {
        return $query->where('report_type', 'user');
    }

    /**
     * Scope for department-specific reports.
     */
    public function scopeDepartmentSpecific($query)
    {
        return $query->where('report_type', 'department');
    }

    /**
     * Scope for reports by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    /**
     * Get the formatted date range.
     */
    public function getDateRangeAttribute(): string
    {
        return Carbon::parse($this->start_date)->format('M d, Y') . ' - ' . 
               Carbon::parse($this->end_date)->format('M d, Y');
    }

    /**
     * Get the number of days in the report period.
     */
    public function getDaysCountAttribute(): int
    {
        return Carbon::parse($this->start_date)
            ->diffInDays(Carbon::parse($this->end_date)) + 1;
    }

    /**
     * Check if the report is ready for download.
     */
    public function isReady(): bool
    {
        return $this->is_generated === 'Yes' 
            && $this->status === 'completed'
            && !empty($this->file_path)
            && file_exists(\App\Models\GeneralReport::storagePath($this->file_path));
    }

    /**
     * Check if the report generation failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the report is being processed.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Get the file size in human-readable format.
     */
    public function getFileSizeAttribute(): ?string
    {
        if (!$this->file_path || !file_exists(\App\Models\GeneralReport::storagePath($this->file_path))) {
            return null;
        }

        $bytes = filesize(\App\Models\GeneralReport::storagePath($this->file_path));
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'processing' => 'info',
            'pending' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Mark the report as processing.
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => 'processing',
            'is_generated' => 'No',
        ]);
    }

    /**
     * Mark the report as completed.
     */
    public function markAsCompleted(string $filePath, array $stats = []): bool
    {
        return $this->update([
            'status' => 'completed',
            'is_generated' => 'Yes',
            'file_path' => $filePath,
            'total_employees' => $stats['total_employees'] ?? null,
            'total_records' => $stats['total_records'] ?? null,
            'generation_time' => $stats['generation_time'] ?? null,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the report as failed.
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => 'failed',
            'is_generated' => 'No',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Delete the associated file.
     */
    public function deleteFile(): bool
    {
        if ($this->file_path && file_exists(\App\Models\GeneralReport::storagePath($this->file_path))) {
            return unlink(\App\Models\GeneralReport::storagePath($this->file_path));
        }
        return false;
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete file when report is deleted
        static::deleting(function ($report) {
            $report->deleteFile();
        });
    }

    /**
     * Where a generated report file lives: private storage (storage/app),
     * never the public folder, so only the signed-in route can serve it.
     */
    public static function storagePath(?string $relative): string
    {
        return storage_path('app/' . ltrim((string) $relative, '/'));
    }
}
