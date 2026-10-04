<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\AttendanceEngine;
use App\Services\AttendanceStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The dashboard's charts and the spreadsheet exports: same figures as
 * everything else, limited to what the viewer may see.
 */
class DashboardAndExportsTest extends TestCase
{
    private Department $cs;
    private User $hod;
    private User $hr;
    private User $lecturer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $faculty = $this->makeFaculty(['name' => 'Faculty of Science']);
        $this->cs = $this->makeDepartment(['name' => 'Computer Science', 'type' => Department::ACADEMIC, 'faculty_id' => $faculty->id]);
        $finance = $this->makeDepartment(['name' => 'Finance']);
        $this->hod = $this->makeUser(['name' => 'Head CS', 'department_id' => $this->cs->id], ['hod']);
        $this->hr = $this->makeUser(['name' => 'HR Officer'], ['hr']);
        $this->lecturer = $this->makeUser(['name' => 'Lecturer Lee', 'department_id' => $this->cs->id]);
        $this->outsider = $this->makeUser(['name' => 'Finance Fiona', 'department_id' => $finance->id]);
        $this->cs->update(['hod_id' => $this->hod->id]);

        $this->clockIn($this->lecturer, '2026-10-05 07:50:00', '2026-10-05 17:05:00', '2026-10-06 08:20:00', '2026-10-06 17:00:00');
        $this->clockIn($this->outsider, '2026-10-05 07:40:00', '2026-10-05 17:00:00');
        app(AttendanceEngine::class)->processRange('2026-10-01', '2026-10-07');
    }

    public function test_dashboard_on_a_weekend_describes_the_last_working_day()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00:00')); // Sunday
        $this->clockIn($this->lecturer, '2026-10-09 07:45:00');
        app(AttendanceEngine::class)->processRange('2026-10-08', '2026-10-11');

        $this->actingAs($this->hr, 'admin')->get('/')
            ->assertOk()
            ->assertSee('Friday 9 October')
            ->assertSee('Sunday is not a working day');
    }

    public function test_dashboard_charts_carry_the_same_month_totals()
    {
        $html = $this->actingAs($this->hr, 'admin')->get('/')->assertOk()->getContent();
        preg_match_all('/data-chart="([^"]+)"/', $html, $m);
        $charts = array_map(fn ($json) => json_decode(html_entity_decode($json), true), $m[1]);
        $this->assertNotEmpty($charts, 'the dashboard draws its charts');

        $donut = collect($charts)->firstWhere('kind', 'donut');
        $s = AttendanceStats::summary(null, today()->startOfMonth(), today());
        $this->assertSame(
            [$s['on_time'], $s['late'], $s['absent'], $s['on_leave']],
            array_column($donut['series'], 'data'),
            'the month donut shows the official month totals'
        );

        $daily = collect($charts)->first(fn ($c) => $c['kind'] === 'columns' && ($c['stacked'] ?? false) && isset($c['count']));
        $this->assertSame($s['late'], array_sum($daily['series'][1]['data']), 'the daily chart adds up to the same late arrivals');
    }

    public function test_summary_spreadsheet_has_everyone_in_scope_and_nobody_else()
    {
        $csv = $this->actingAs($this->hr, 'admin')->get('/reports/summary.csv?scope=university&period=this_month');
        $csv->assertOk();
        $this->assertStringStartsWith('text/csv', $csv->headers->get('content-type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Lecturer Lee', $body);
        $this->assertStringContainsString('Finance Fiona', $body);

        $this->actingAs($this->hod, 'admin')->get('/reports/summary.csv?scope=university')->assertStatus(403);
        $own = $this->actingAs($this->hod, 'admin')->get("/reports/summary.csv?scope=department:{$this->cs->id}&period=this_month");
        $own->assertOk();
        $this->assertStringContainsString('Lecturer Lee', $own->streamedContent());
        $this->assertStringNotContainsString('Finance Fiona', $own->streamedContent());
    }

    public function test_every_report_has_a_spreadsheet_version()
    {
        foreach ([
            '/reports/daily.csv?scope=university&date=2026-10-05' => 'Lecturer Lee',
            "/reports/individual.csv?user={$this->lecturer->id}&period=this_month" => '2026-10-06',
            '/reports/leave.csv?scope=university&period=this_month' => 'Reference',
        ] as $url => $expect) {
            $res = $this->actingAs($this->hr, 'admin')->get($url);
            $res->assertOk();
            $this->assertStringContainsString($expect, $res->streamedContent(), $url);
        }
    }

    public function test_spreadsheet_cells_never_become_formulas()
    {
        $this->lecturer->forceFill(['position' => '=HYPERLINK("http://evil.example","x")'])->save();
        $body = $this->actingAs($this->hr, 'admin')->get('/reports/summary.csv?scope=university&period=this_month')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString(',"=HYPERLINK', $body);
    }
}
