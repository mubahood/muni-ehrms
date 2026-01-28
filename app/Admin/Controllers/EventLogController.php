<?php

namespace App\Admin\Controllers;

use App\Models\EventLog;
use App\Models\User;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Carbon\Carbon;

class EventLogController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Hikvision Event Logs';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new EventLog());

        // Default sorting - newest first
        $grid->model()->orderBy('event_time', 'desc');

        // Disable create - events come from webhook
        $grid->disableCreateButton();

        // Enable export
        $grid->export(function ($export) {
            $export->filename('event_logs_' . date('Y-m-d'));
        });

        // Quick search
        $grid->quickSearch(['employee_no', 'employee_name', 'card_no', 'event_serial_no']);

        // =====================================================================
        // FILTERS
        // =====================================================================
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            // Date range filter
            $filter->between('event_time', 'Event Time')->datetime();

            // Employee number
            $filter->like('employee_no', 'Employee No');

            // Employee name
            $filter->like('employee_name', 'Employee Name');

            // Process status
            $filter->equal('process_status', 'Status')->select([
                EventLog::STATUS_UNPROCESSED => 'Unprocessed',
                EventLog::STATUS_PROCESSED => 'Processed',
                EventLog::STATUS_FAILED => 'Failed',
                EventLog::STATUS_SKIPPED => 'Skipped',
            ]);

            // Verify mode
            $filter->equal('verify_mode', 'Verify Mode')->select([
                'face' => 'Face Recognition',
                'card' => 'Card',
                'fingerprint' => 'Fingerprint',
                'password' => 'Password',
            ]);

            // Source
            $filter->equal('source', 'Source')->select([
                'webhook' => 'Webhook',
                'import' => 'Import',
                'manual' => 'Manual',
            ]);

            // User linked
            $filter->where(function ($query) {
                if ($this->input == 'linked') {
                    $query->whereNotNull('user_id');
                } else {
                    $query->whereNull('user_id');
                }
            }, 'User Link')->select([
                'linked' => 'Linked',
                'not_linked' => 'Not Linked',
            ]);
        });

        // =====================================================================
        // GRID COLUMNS
        // =====================================================================

        $grid->column('id', __('ID'))->sortable()->width(60);

        $grid->column('event_time', __('Event Time'))
            ->display(function ($time) {
                if (!$time) return '-';
                return Carbon::parse($time)->format('M d, H:i:s');
            })
            ->sortable()
            ->width(120);

        $grid->column('employee_no', __('Emp ID'))
            ->display(function ($no) {
                return $no ? "<strong>{$no}</strong>" : '<span class="text-muted">-</span>';
            })
            ->sortable()
            ->width(80);

        $grid->column('employee_name', __('Name'))
            ->display(function ($name) {
                return $name ?? '-';
            })
            ->sortable();

        $grid->column('event_type', __('Event Type'))
            ->display(function () {
                $type = EventLog::getEventTypeName($this->major, $this->minor);
                $badge = $this->major == 5 && in_array($this->minor, [75, 76, 77]) ? 'success' : 'info';
                return "<span class='label label-{$badge}'>{$type}</span>";
            });

        $grid->column('major', __('Maj'))->width(50);
        $grid->column('minor', __('Min'))->width(50);

        $grid->column('verify_mode', __('Method'))
            ->display(function ($mode) {
                $icons = [
                    'face' => '<i class="fa fa-user-circle text-primary"></i> Face',
                    'card' => '<i class="fa fa-credit-card text-success"></i> Card',
                    'fingerprint' => '<i class="fa fa-hand-paper-o text-warning"></i> Fingerprint',
                    'faceOrFpOrCardOrPw' => '<i class="fa fa-star text-info"></i> Multi',
                ];
                return $icons[$mode] ?? ($mode ?? '-');
            })
            ->width(100);

        $grid->column('process_status', __('Status'))
            ->using([
                EventLog::STATUS_UNPROCESSED => 'Unprocessed',
                EventLog::STATUS_PROCESSED => 'Processed',
                EventLog::STATUS_FAILED => 'Failed',
                EventLog::STATUS_SKIPPED => 'Skipped',
            ])
            ->label([
                EventLog::STATUS_UNPROCESSED => 'warning',
                EventLog::STATUS_PROCESSED => 'success',
                EventLog::STATUS_FAILED => 'danger',
                EventLog::STATUS_SKIPPED => 'default',
            ])
            ->filter([
                EventLog::STATUS_UNPROCESSED => 'Unprocessed',
                EventLog::STATUS_PROCESSED => 'Processed',
                EventLog::STATUS_FAILED => 'Failed',
                EventLog::STATUS_SKIPPED => 'Skipped',
            ])
            ->width(100);

        $grid->column('process_error', __('Skip/Error Reason'))
            ->display(function ($error) {
                if (!$error) return '-';
                $short = strlen($error) > 50 ? substr($error, 0, 50) . '...' : $error;
                return "<span class='text-danger' title='{$error}'>{$short}</span>";
            })
            ->width(200);

        $grid->column('user.name', __('Linked User'))
            ->display(function ($name) {
                return $name ? "<span class='text-success'><i class='fa fa-check-circle'></i> {$name}</span>" : '<span class="text-muted">Not Linked</span>';
            })
            ->width(150);

        $grid->column('door_no', __('Door'))
            ->display(function ($door) {
                return $door ?? '-';
            })
            ->width(80);

        $grid->column('device_ip', __('Device IP'))
            ->display(function ($ip) {
                return $ip ? "<code>{$ip}</code>" : '-';
            })
            ->width(120);

        $grid->column('card_no', __('Card'))
            ->display(function ($card) {
                return $card ?? '-';
            })
            ->hide();

        $grid->column('picture_url', __('Picture'))
            ->display(function ($url) {
                if (!$url) return '-';
                return "<a href='{$url}' target='_blank'><i class='fa fa-camera'></i> View</a>";
            })
            ->hide();

        $grid->column('temperature', __('Temp'))
            ->display(function ($temp) {
                if (!$temp) return '-';
                $color = $temp > 37.3 ? 'danger' : 'success';
                return "<span class='label label-{$color}'>{$temp}°C</span>";
            })
            ->hide();

        $grid->column('mask_detected', __('Mask'))
            ->display(function ($mask) {
                if ($mask === null) return '-';
                return $mask ? '<i class="fa fa-check text-success"></i> Yes' : '<i class="fa fa-times text-danger"></i> No';
            })
            ->hide();

        $grid->column('device_name', __('Device'))
            ->display(function ($ip) {
                return $ip ?? '-';
            })
            ->hide();

        $grid->column('source', __('Source'))
            ->label([
                'webhook' => 'primary',
                'import' => 'info',
                'manual' => 'default',
            ])
            ->hide();

        $grid->column('created_at', __('Received'))
            ->display(function ($time) {
                return Carbon::parse($time)->diffForHumans();
            })
            ->sortable();

        // =====================================================================
        // BATCH ACTIONS
        // =====================================================================

        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

        // =====================================================================
        // ROW ACTIONS
        // =====================================================================

        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
        });

        // =====================================================================
        // TOOLS
        // =====================================================================

        $grid->tools(function ($tools) {
            // Add process unprocessed button
            $tools->append('<a
            target="_blank"
            href="' . url('process-event-logs') . '" class="btn btn-sm btn-warning" style="margin-right: 5px;">
                <i class="fa fa-cog"></i> Process Unprocessed
            </a>');

            // Add stats
            $unprocessed = EventLog::unprocessed()->count();
            $processed = EventLog::processed()->count();
            $failed = EventLog::failed()->count();

            $tools->append("
                <span class='label label-warning' style='margin-right: 5px;'>Unprocessed: {$unprocessed}</span>
                <span class='label label-success' style='margin-right: 5px;'>Processed: {$processed}</span>
                <span class='label label-danger'>Failed: {$failed}</span>
            ");
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(EventLog::findOrFail($id));

        $show->panel()->title('Event Log Details');

        // Event Information
        $show->divider('Event Information');
        $show->field('id', __('ID'));
        $show->field('event_serial_no', __('Event Serial No'));
        $show->field('event_index_code', __('Event Index Code'));
        $show->field('event_time', __('Event Time'))->as(function ($time) {
            return $time ? Carbon::parse($time)->format('M d, Y H:i:s') : '-';
        });

        // Classification
        $show->divider('Event Classification');
        $show->field('major', __('Major Code'));
        $show->field('minor', __('Minor Code'));
        $show->field('event_type', __('Event Type'))->as(function () {
            return EventLog::getEventTypeName($this->major, $this->minor);
        });

        // Person Information
        $show->divider('Person Information');
        $show->field('employee_no', __('Employee No'));
        $show->field('employee_name', __('Employee Name'));
        $show->field('card_no', __('Card No'));
        $show->field('verify_mode', __('Verification Mode'));

        // Health Data
        $show->divider('Health Data');
        $show->field('mask_detected', __('Mask Detected'))->as(function ($mask) {
            if ($mask === null) return 'N/A';
            return $mask ? 'Yes' : 'No';
        });
        $show->field('temperature', __('Temperature'))->as(function ($temp) {
            return $temp ? "{$temp}°C" : 'N/A';
        });

        // Device Information
        $show->divider('Device Information');
        $show->field('device_serial', __('Device Serial'));
        $show->field('device_ip', __('Device IP'));
        $show->field('device_name', __('Device Name'));
        $show->field('door_no', __('Door No'));

        // Processing Status
        $show->divider('Processing Status');
        $show->field('process_status', __('Status'))->unescape()->as(function ($status) {
            $colors = [
                'unprocessed' => 'warning',
                'processed' => 'success',
                'failed' => 'danger',
                'skipped' => 'default',
            ];
            $color = $colors[$status] ?? 'default';
            return "<span class='label label-{$color}'>" . ucfirst($status) . "</span>";
        });
        $show->field('process_error', __('Error Message'));
        $show->field('processed_at', __('Processed At'));

        // Linked Records
        $show->divider('Linked Records');
        $show->field('user.name', __('Linked User'));
        $show->field('attendance_record_id', __('Attendance Record ID'));

        // Source
        $show->divider('Source Information');
        $show->field('source', __('Source'));
        $show->field('batch_id', __('Batch ID'));
        $show->field('created_at', __('Received At'));

        // Raw Data
        $show->divider('Raw Data');
        $show->field('raw_data', __('Raw JSON'))->unescape()->as(function ($data) {
            if (!$data) return 'N/A';
            return '<pre style="max-height: 400px; overflow: auto;">' .
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                '</pre>';
        });

        // Picture
        if ($show->getModel()->has_picture && $show->getModel()->picture_url) {
            $show->divider('Captured Picture');
            $show->field('picture_url', __('Picture'))->unescape()->as(function ($url) {
                return "<img src='{$url}' style='max-width: 300px;' alt='Captured Picture'>";
            });
        }

        return $show;
    }

    /**
     * Make a form builder.
     * Note: Events are typically created via webhook, not manually
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new EventLog());

        // For manual corrections only
        $form->display('id', __('ID'));
        $form->display('event_serial_no', __('Event Serial No'));
        $form->display('event_time', __('Event Time'));
        $form->display('employee_no', __('Employee No'));
        $form->display('employee_name', __('Employee Name'));

        $form->divider('Processing Status');

        $form->select('process_status', __('Status'))->options([
            EventLog::STATUS_UNPROCESSED => 'Unprocessed',
            EventLog::STATUS_PROCESSED => 'Processed',
            EventLog::STATUS_FAILED => 'Failed',
            EventLog::STATUS_SKIPPED => 'Skipped',
        ]);

        $form->textarea('process_error', __('Error Message'));

        $form->select('user_id', __('Link User'))->options(
            User::pluck('name', 'id')
        )->help('Manually link this event to a user');

        return $form;
    }

    /**
     * Dashboard view with statistics
     */
    public function dashboard(Content $content)
    {
        return $content
            ->title('Event Log Dashboard')
            ->description('Overview of Hikvision events')
            ->row(function ($row) {
                // Statistics
                $total = EventLog::count();
                $today = EventLog::whereDate('event_time', today())->count();
                $unprocessed = EventLog::unprocessed()->count();
                $processed = EventLog::processed()->count();
                $failed = EventLog::failed()->count();

                $row->column(3, new Box('Total Events', "<h2>{$total}</h2>"));
                $row->column(3, new Box('Today\'s Events', "<h2>{$today}</h2>"));
                $row->column(3, new Box('Unprocessed', "<h2 class='text-warning'>{$unprocessed}</h2>"));
                $row->column(3, new Box('Failed', "<h2 class='text-danger'>{$failed}</h2>"));
            });
    }
}
