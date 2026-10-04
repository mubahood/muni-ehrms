<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Audit;
use App\Services\DemoSandbox;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * Administration → Demo data (System Administrator only; never a demo
 * account). Shows the sandbox, switches the demo sign-in panel on the login
 * page, deletes demo accounts one by one or all together, and rebuilds the
 * sandbox with fresh dates.
 */
class DemoDataController extends Controller
{
    public function index(Content $content)
    {
        $accounts = User::with('department', 'roles')->where('is_demo', true)
            ->whereIn('username', collect(config('demo.accounts'))->pluck('username'))->orderBy('id')->get();
        $lastSeen = AuditLog::where('action', 'auth.login')->whereIn('user_id', $accounts->pluck('id'))
            ->selectRaw('user_id, MAX(created_at) AS at, COUNT(*) AS n')->groupBy('user_id')->get()->keyBy('user_id');

        return $content->title('Demo data')
            ->description('The demonstration university: sealed off from real staff and records')
            ->body(view('ehrms.demo-data', [
                'stats' => DemoSandbox::stats(),
                'accounts' => $accounts,
                'lastSeen' => $lastSeen,
                'loginsOn' => (bool) SystemConfiguration::current()->demo_logins,
                'password' => config('demo.password'),
                'weekly' => (bool) config('demo.weekly_reset'),
            ]));
    }

    public function logins(Request $request)
    {
        $on = $request->boolean('enabled');
        SystemConfiguration::current()->update(['demo_logins' => $on]);
        Audit::log('demo.logins', $on ? 'Showed the demo sign-ins on the login page' : 'Hid the demo sign-ins from the login page');
        admin_toastr($on ? 'Demo sign-ins are now offered on the login page.' : 'Demo sign-ins are hidden from the login page.');

        return redirect(admin_url('demo-data'));
    }

    public function rebuild()
    {
        @set_time_limit(300);
        $removed = DemoSandbox::exists() ? DemoSandbox::purge() : 0;
        $stats = (new DemoSandbox())->build();
        Audit::log('demo.rebuilt', "Rebuilt the demo sandbox ({$removed} account(s) replaced by {$stats['accounts']})");
        admin_toastr("Demo data rebuilt: {$stats['accounts']} accounts, three months of attendance and {$stats['leave']} leave requests.");

        return redirect(admin_url('demo-data'));
    }

    public function purge()
    {
        $removed = DemoSandbox::purge();
        SystemConfiguration::current()->update(['demo_logins' => false]);
        Audit::log('demo.deleted', "Deleted all demo data ({$removed} demo account(s)); demo sign-ins hidden");
        admin_toastr("All demo data deleted ({$removed} accounts). Real staff and records were not touched.");

        return redirect(admin_url('demo-data'));
    }

    public function destroy(int $user)
    {
        $account = User::where('is_demo', true)->findOrFail($user);
        DemoSandbox::purge([$account->id]);
        Audit::log('demo.deleted', "Deleted demo account {$account->username} ({$account->name}) and its data");

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => "{$account->name} deleted."]);
        }
        admin_toastr("{$account->name} ({$account->username}) and their data deleted.");

        return redirect(admin_url('demo-data'));
    }

    /**
     * Explore the demo as its System Administrator without signing out: the
     * real account is remembered in the session and "Back to my account"
     * returns to it.
     */
    public function enter(Request $request)
    {
        $me = \Encore\Admin\Facades\Admin::user();
        $demo = User::where('is_demo', true)->where('username', 'demo.admin')->where('status', 'Active')->first();
        if (!$demo) {
            admin_toastr('There is no demo data to explore. Build it first.', 'error');

            return redirect(admin_url('demo-data'));
        }
        Audit::log('demo.entered', 'Opened the demo university as ' . $demo->username);
        \Illuminate\Support\Facades\Auth::guard('admin')->login($demo);
        $request->session()->regenerate();
        $request->session()->put('ehr_demo_return', [$me->id, hash_hmac('sha256', (string) $me->id, config('app.key'))]);

        return redirect(admin_url('/'));
    }

    /** Back from the demo to the real account that opened it. */
    public static function leave(Request $request)
    {
        [$id, $sig] = $request->session()->get('ehr_demo_return', [null, null]);
        $current = \Illuminate\Support\Facades\Auth::guard('admin')->user();
        $real = $id && hash_equals(hash_hmac('sha256', (string) $id, config('app.key')), (string) $sig)
            ? User::where('is_demo', false)->where('status', 'Active')->find($id) : null;
        abort_unless($real && $current && $current->isDemo(), 403);

        \Illuminate\Support\Facades\Auth::guard('admin')->login($real);
        $request->session()->forget('ehr_demo_return');
        $request->session()->regenerate();
        Audit::log('demo.left', 'Returned from the demo university', null, $real);

        return redirect(admin_url('/'));
    }
}
