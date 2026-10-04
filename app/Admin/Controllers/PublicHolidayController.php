<?php

namespace App\Admin\Controllers;

use App\Models\PublicHoliday;
use App\Services\AccessPolicy;
use App\Services\Audit;
use App\Support\UgandaHolidays;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Validation\Rule;

/**
 * Public holidays. Nobody is absent or late on one, and leave does not count
 * it. Changing one rebuilds the attendance of the dates concerned.
 */
class PublicHolidayController extends AdminController
{
    protected $title = 'Public holidays';

    protected $description = [
        'index' => 'Days nobody is expected at work',
        'show' => 'Details',
        'edit' => 'Edit',
        'create' => 'New',
    ];

    protected function grid(): Grid
    {
        $this->ensureYears();
        $grid = new Grid(new PublicHoliday());
        $grid->model()->orderBy('date');
        $year = (int) request('year', now()->year);
        $grid->model()->whereYear('date', $year);
        $canManage = AccessPolicy::allows(Admin::user(), 'holidays.manage');
        if (!$canManage) {
            $grid->disableCreateButton();
            $grid->disableActions();
        }
        $grid->disableBatchActions();
        $grid->disablePagination();
        $grid->disableFilter();
        $grid->actions(fn ($actions) => $actions->disableView());
        $grid->header(function () use ($year) {
            $links = collect(range(now()->year - 1, now()->year + 1))->map(function ($y) use ($year) {
                return '<a href="' . admin_url('public-holidays?year=' . $y) . '" class="' . ($y === $year ? 'on' : '') . '">' . $y . '</a>';
            })->implode('');

            return '<nav class="ehr-tabs" style="margin:0">' . $links . '</nav>'
                . '<div class="ehr-note" style="margin-top:14px">Eid al-Fitr and Eid al-Adha follow the moon: add them once gazetted.</div>';
        });

        $grid->column('date', 'Date')->display(fn ($d) => Carbon::parse($d)->format('l j F Y'));
        $grid->column('name', 'Holiday');
        $grid->column('when', ' ')->display(function () {
            $d = Carbon::parse($this->date);
            if ($d->isToday()) {
                return '<span class="st st-leave">Today</span>';
            }

            return $d->isPast() ? '<span class="cell-sub">Passed</span>' : '<span class="cell-sub">' . $d->diffForHumans() . '</span>';
        });

        return $grid;
    }

    protected function detail($id): Show
    {
        return new Show(PublicHoliday::findOrFail($id));
    }

    protected function form(): Form
    {
        $form = new Form(new PublicHoliday());
        $id = request()->route('public_holiday');
        $form->date('date', 'Date')->rules(['required', 'date', Rule::unique('public_holidays', 'date')->ignore($id)]);
        $form->text('name', 'Holiday')->rules('required|max:255');
        $form->saved(fn (Form $form) => Audit::log('holiday.changed', "Public holiday {$form->model()->name} on {$form->model()->date->toDateString()}", $form->model()));
        $form->deleted(fn () => Audit::log('holiday.changed', 'A public holiday was removed'));
        $form->tools(fn ($tools) => $tools->disableView());

        return $form;
    }

    /** Make sure this year's and next year's statutory holidays exist. */
    private function ensureYears(): void
    {
        foreach ([now()->year, now()->year + 1] as $year) {
            if (!PublicHoliday::whereYear('date', $year)->exists()) {
                foreach (UgandaHolidays::forYear($year) as $date => $name) {
                    PublicHoliday::firstOrCreate(['date' => $date], ['name' => $name]);
                }
            }
        }
    }
}
