<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Whose records a person may see.
 *
 *   System Administrator, Human Resource, University Secretary → the whole university
 *   Faculty Dean        → every department of the faculties they lead
 *   Head of Department  → the departments they head
 *   Employee            → themselves
 *
 * Deans and Heads always see themselves too. A Head of Department with no
 * department set as headed by them falls back to their own department when
 * that department has no Head set yet, so a newly appointed Head is not left
 * looking at an empty system (the same rule decides who approves its leave).
 */
class Scope
{
    public const UNIVERSITY = 'university';
    public const FACULTY = 'faculty';
    public const DEPARTMENT = 'department';
    public const SELF = 'self';

    public static function level(User $viewer): string
    {
        if ($viewer->hasAnyRole('admin', 'hr', 'us')) {
            return self::UNIVERSITY;
        }
        if ($viewer->hasAnyRole('dean') && $viewer->deanOfFaculties()->exists()) {
            return self::FACULTY;
        }
        if ($viewer->hasAnyRole('hod', 'dean') && self::departmentIds($viewer)) {
            return self::DEPARTMENT;
        }

        return self::SELF;
    }

    /**
     * Ids of departments in the viewer's scope; null means all.
     *
     * @return int[]|null
     */
    public static function departmentIds(User $viewer): ?array
    {
        if ($viewer->hasAnyRole('admin', 'hr', 'us')) {
            return Department::where('is_demo', $viewer->isDemo())->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $ids = [];
        if ($viewer->hasAnyRole('dean')) {
            $facultyIds = $viewer->deanOfFaculties()->pluck('id');
            $ids = Department::whereIn('faculty_id', $facultyIds)->pluck('id')->all();
        }
        if ($viewer->hasAnyRole('hod', 'dean')) {
            $headed = $viewer->headedDepartments()->pluck('id')->all();
            if (!$headed && $viewer->hasAnyRole('hod') && $viewer->department_id
                && Department::whereKey($viewer->department_id)->whereNull('hod_id')->exists()) {
                $headed = [(int) $viewer->department_id];
            }
            $ids = array_merge($ids, $headed);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Ids of people in the viewer's scope; null means everyone.
     *
     * @return int[]|null
     */
    public static function userIds(User $viewer): ?array
    {
        if ($viewer->hasAnyRole('admin', 'hr', 'us')) {
            // Everyone in the viewer's world, people without a department included.
            $ids = User::where('is_demo', $viewer->isDemo())->pluck('id')->all();
        } else {
            $departments = self::departmentIds($viewer);
            $ids = $departments ? User::whereIn('department_id', $departments)->where('is_demo', $viewer->isDemo())->pluck('id')->all() : [];
        }
        $ids[] = $viewer->id;

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Narrow a query on users (or on anything with the given user column) to the scope. */
    public static function apply($query, User $viewer, string $userColumn = 'id')
    {
        $ids = self::userIds($viewer);

        return $ids === null ? $query : $query->whereIn($userColumn, $ids);
    }

    public static function canSee(User $viewer, int $userId): bool
    {
        $ids = self::userIds($viewer);

        return $ids === null || in_array($userId, $ids, true);
    }

    /** Departments the viewer may filter by. */
    public static function departments(User $viewer): Collection
    {
        $ids = self::departmentIds($viewer);
        $query = Department::query()->where('is_active', true)->orderBy('name');

        return $ids === null ? $query->get() : $query->whereIn('id', $ids)->get();
    }

    /** Faculties the viewer may filter by. */
    public static function faculties(User $viewer): Collection
    {
        $level = self::level($viewer);
        if ($level === self::UNIVERSITY) {
            return Faculty::active()->where('is_demo', $viewer->isDemo())->orderBy('name')->get();
        }
        if ($level === self::FACULTY) {
            return $viewer->deanOfFaculties()->orderBy('name')->get();
        }

        return collect();
    }

    /** "Whole university", "Faculty of Science", "Computer Science", "Your own records". */
    public static function label(User $viewer): string
    {
        switch (self::level($viewer)) {
            case self::UNIVERSITY:
                return 'Whole university';
            case self::FACULTY:
                return $viewer->deanOfFaculties()->pluck('name')->implode(', ');
            case self::DEPARTMENT:
                return Department::whereIn('id', self::departmentIds($viewer))->orderBy('name')->pluck('name')->implode(', ');
            default:
                return 'Your own records';
        }
    }
}
