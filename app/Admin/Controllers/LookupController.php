<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Scope;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;

/**
 * Search endpoints behind the people pickers. Results never go beyond what
 * the person searching may see: their scope, or with ?colleagues=1 the active
 * staff of their own department (for "staff left to take charge").
 */
class LookupController extends Controller
{
    private const PER_PAGE = 20;

    public function people(Request $request)
    {
        /** @var User $me */
        $me = Admin::user();
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));

        $query = User::query()->with('department')->where('status', 'Active')->where('is_demo', $me->isDemo());
        if ($request->boolean('colleagues')) {
            $query->where('id', '!=', $me->id)->where('department_id', $me->department_id ?: 0);
        } else {
            Scope::apply($query, $me);
        }
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $q->where('name', 'like', $like)->orWhere('employee_no', 'like', $like)->orWhere('username', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $people = $query->orderBy('name')->forPage($page, self::PER_PAGE)->get();

        return response()->json([
            'results' => $people->map(fn (User $u) => [
                'id' => $u->id,
                'text' => $u->displayName(),
                'sub' => collect([$u->employee_no, $u->position, optional($u->department)->name])->filter()->implode(' · '),
            ])->values(),
            'more' => $page * self::PER_PAGE < $total,
        ]);
    }
}
