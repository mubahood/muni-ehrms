<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Services\Audit;
use Encore\Admin\Controllers\AuthController as BaseAuthController;
use Encore\Admin\Form;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseAuthController
{
    /**
     * Handle a login request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postLogin(Request $request)
    {
        $credentials = $request->only(['username', 'password']);

        $validator = Validator::make($credentials, [
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $loginValue = trim((string) $request->input('username'));
        $password = (string) $request->input('password');
        $remember = $request->has('remember');

        // Brute-force protection: 5 failures per account and address lock that
        // pair for a minute; 30 failures from one address lock it for 10 minutes.
        $accountKey = 'login:' . sha1(mb_strtolower($loginValue) . '|' . $request->ip());
        $addressKey = 'login-ip:' . sha1((string) $request->ip());
        foreach ([[$accountKey, 5], [$addressKey, 30]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = RateLimiter::availableIn($key);
                Audit::log('auth.locked', 'Sign-in locked after repeated failures for "' . mb_substr($loginValue, 0, 60) . '"');

                return back()->withInput($request->only('username'))->withErrors([
                    'username' => 'Too many failed attempts. Try again in ' . ($wait > 60 ? ceil($wait / 60) . ' minutes' : $wait . ' seconds') . '.',
                ]);
            }
        }

        // Only active accounts may sign in; by username, or by e-mail address.
        $guard = Auth::guard('admin');
        if ($guard->attempt(['username' => $loginValue, 'password' => $password, 'status' => 'Active'], $remember)
            || (filter_var($loginValue, FILTER_VALIDATE_EMAIL) && $guard->attempt(['email' => $loginValue, 'password' => $password, 'status' => 'Active'], $remember))) {
            RateLimiter::clear($accountKey);
            $request->session()->regenerate();
            $user = $guard->user();
            Audit::log('auth.login', 'Signed in as ' . $user->roleLabel() . ($user->isDemo() ? ' (demo account)' : ''), null, $user);

            if ($user->must_change_password && !$user->isDemo()) {
                session()->flash('ehr_password_notice', true);

                return redirect(admin_url('auth/setting'));
            }

            return redirect()->intended(admin_url('/'));
        }

        RateLimiter::hit($accountKey, 60);
        RateLimiter::hit($addressKey, 600);
        Audit::log('auth.failed', 'Failed sign-in for username "' . mb_substr($loginValue, 0, 60) . '"');

        return back()->withInput($request->only('username', 'remember'))->withErrors([
            'username' => 'These credentials do not match an active account.',
        ]);
    }

    /**
     * Your profile: name, photo and password. A new password must be strong
     * (see strongPassword()); demo accounts cannot change theirs, so the demo
     * sign-ins keep working for everyone.
     */
    protected function settingForm()
    {
        $form = new Form(new User());
        /** @var User $me */
        $me = Admin::user();

        if (session('ehr_password_notice') || $me->must_change_password) {
            $form->html('<div class="ehr-note warn"><b>Please choose a new password.</b> Your current password is too easy to guess. '
                . 'Pick one of at least 4 characters; you can continue as soon as it is saved.</div>');
        }

        $form->text('first_name', 'First name')->rules('required|max:60');
        $form->text('last_name', 'Surname')->rules('required|max:60');
        $form->image('avatar', 'Profile photo')->uniqueName()->rules('nullable|image|max:2048');
        $form->divider('Sign-in');
        $form->display('username', 'Username');
        $form->display('email', 'E-mail');

        if ($me->isDemo()) {
            $form->html('<div class="ehr-note">This is a shared demo account, so its password cannot be changed.</div>');
        } else {
            $form->password('password', 'New password')
                ->rules(['nullable', 'confirmed', self::strongPassword($me)])
                ->help('Leave empty to keep your current password. At least 4 characters.')
                ->default('');
            $form->password('password_confirmation', 'Repeat new password')->default('');
        }

        $form->setAction(admin_url('auth/setting'));
        $form->ignore(['password_confirmation']);
        $form->tools(fn ($tools) => $tools->disableList()->disableDelete()->disableView());

        $form->saving(function (Form $form) use ($me) {
            // Only your own record, and only these fields.
            abort_unless((int) $form->model()->id === (int) $me->id, 403);
            $form->model()->name = trim($form->first_name . ' ' . $form->last_name);
            if ($me->isDemo() || !$form->password) {
                $form->ignore(['password']);

                return;
            }
            $form->password = Hash::make($form->password);
            $form->model()->must_change_password = false;
            $form->model()->password_changed_at = now();
            $form->model()->has_changed_password = 'Yes';
        });

        $form->saved(function () {
            Audit::log('auth.profile', 'Updated their profile');
            admin_toastr('Your profile is saved.');

            return redirect(admin_url('auth/setting'));
        });

        return $form;
    }

    /** A new password needs at least 4 characters. */
    public static function strongPassword(User $user): string
    {
        return 'min:4';
    }

    /**
     * The demo accounts offered on the login page, grouped by role. Nothing
     * unless the System Administrator has switched the panel on (Demo data)
     * and the demo sandbox exists, so a real deployment never shows sign-ins
     * by accident.
     *
     * @return array<string, array<int, array{username:string, name:string, position:string, department:?string, initials:string}>>
     */
    public static function demoAccounts(): array
    {
        try {
            if (!\App\Models\SystemConfiguration::current()->demo_logins) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $groups = [
            'admin' => 'Administration', 'us' => 'Administration', 'hr' => 'Human Resource',
            'dean' => 'Faculty Deans', 'hod' => 'Heads of Department', 'employee' => 'Staff',
        ];
        $out = array_fill_keys(array_unique(array_values($groups)), []);
        $users = User::with('department', 'roles')->where('is_demo', true)->where('status', 'Active')
            ->whereIn('username', collect(config('demo.accounts'))->pluck('username'))->orderBy('id')->get();
        foreach ($users as $u) {
            $slugs = $u->roles->pluck('slug')->all();
            $group = 'Staff';
            foreach ($groups as $slug => $label) {
                if (in_array($slug, $slugs, true)) {
                    $group = $label;
                    break;
                }
            }
            $out[$group][] = [
                'username' => $u->username,
                'name' => $u->name,
                'position' => $u->position,
                'department' => optional($u->department)->name,
                'initials' => $u->initials(),
                'role' => $u->roleLabel(),
            ];
        }

        return array_filter($out);
    }
}