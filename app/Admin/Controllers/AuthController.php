<?php

namespace App\Admin\Controllers;

use Encore\Admin\Controllers\AuthController as BaseAuthController;
use Encore\Admin\Form;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $remember = $request->has('remember');
        $loginValue = $request->input('username');
        $password = $request->input('password');

        // Try authenticating by username first, then by email
        if (Auth::guard('admin')->attempt(['username' => $loginValue, 'password' => $password], $remember)) {
            return redirect()->intended('/');
        }

        if (Auth::guard('admin')->attempt(['email' => $loginValue, 'password' => $password], $remember)) {
            return redirect()->intended('/');
        }

        return back()->withInput()->withErrors([
            'username' => 'These credentials do not match our records.',
        ]);
    }
}