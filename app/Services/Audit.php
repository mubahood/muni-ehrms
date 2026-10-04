<?php

namespace App\Services;

use App\Models\AuditLog;
use Encore\Admin\Facades\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Writes the audit trail. Never throws: a failure to record an event must not
 * stop the action being recorded.
 */
class Audit
{
    public static function log(string $action, string $description, ?Model $subject = null, $actor = null): void
    {
        try {
            $actor = $actor ?: (Admin::guard()->check() ? Admin::user() : null);
            $request = request();
            AuditLog::create([
                'user_id' => optional($actor)->id,
                'user_name' => $actor ? ($actor->name ?: $actor->username) : null,
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->getKey() : null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit log write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
