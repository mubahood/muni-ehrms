<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemConfiguration;
use App\Services\AttendanceEngine;
use App\Services\Audit;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * System settings on one page: who the institution is (for letterheads and
 * e-mails) and the rules attendance and leave are worked out by.
 */
class SettingsController extends Controller
{
    private const RULES = ['late_time', 'working_days', 'full_day_hours', 'repeat_capture_minutes', 'start_date'];

    public function edit(Content $content)
    {
        return $content->title('System settings')
            ->description('The institution, and the rules attendance and leave follow')
            ->body(view('ehrms.settings', ['config' => SystemConfiguration::current()]));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:200',
            'office_name' => 'nullable|string|max:150',
            'system_name' => 'required|string|max:150',
            'motto' => 'nullable|string|max:150',
            'company_address' => 'required|string|max:255',
            'company_phone' => 'nullable|string|max:60',
            'company_fax' => 'nullable|string|max:60',
            'company_email' => 'nullable|email|max:120',
            'company_website' => 'nullable|string|max:120',
            'report_footer' => 'nullable|string|max:255',
            'late_time' => 'required|date_format:H:i',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'integer|between:1,7',
            'full_day_hours' => 'required|numeric|min:1|max:24',
            'repeat_capture_minutes' => 'required|integer|min:0|max:240',
            'start_date' => 'required|date',
            'leave_year_start_month' => 'required|integer|between:1,12',
            'annual_leave_days' => 'required|integer|min:0|max:120',
        ], [], ['working_days' => 'working days', 'late_time' => 'late arrival time']);

        $config = SystemConfiguration::current();
        $data['late_time'] .= ':00';
        $data['working_days'] = implode(',', collect($data['working_days'])->map(fn ($d) => (int) $d)->unique()->sort()->all());
        $before = $config->only(self::RULES);
        $config->fill($data)->save();
        SystemConfiguration::forgetCurrent();

        $changed = collect(self::RULES)->filter(fn ($k) => (string) $before[$k] !== (string) $config->fresh()->{$k})->values();
        Audit::log('settings.updated', 'System settings saved' . ($changed->isNotEmpty() ? ' (rules changed: ' . $changed->implode(', ') . ')' : ''), $config);

        if ($changed->isNotEmpty()) {
            $days = app(AttendanceEngine::class)->processRange(now()->startOfMonth(), today());
            admin_toastr("Settings saved. This month's attendance ({$days} days) was recalculated under the new rules; earlier months are unchanged.");
        } else {
            admin_toastr('Settings saved.');
        }

        return redirect(admin_url('settings'));
    }
}
