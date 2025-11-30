<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ config('admin.title') }} | {{ trans('admin.login') }}</title>
    <!-- Responsive -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    @if (!is_null($favicon = Admin::favicon()))
    <link rel="shortcut icon" href="{{ $favicon }}">
    @endif

    <!-- Bootstrap 3.3.5 -->
    <link rel="stylesheet" href="{{ admin_asset('vendor/laravel-admin/AdminLTE/bootstrap/css/bootstrap.min.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ admin_asset('vendor/laravel-admin/font-awesome/css/font-awesome.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ admin_asset('vendor/laravel-admin/AdminLTE/dist/css/AdminLTE.min.css') }}">
    <!-- iCheck -->
    <link rel="stylesheet" href="{{ admin_asset('vendor/laravel-admin/AdminLTE/plugins/iCheck/square/blue.css') }}">

    <style>
        /* Full-screen background + overlay */
        .login-page {
            position: relative;
            min-height: 100vh;
            background: url('assets/images/syama.jpg') no-repeat center center fixed;
            background-size: cover;
        }
        .login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: #134169;
            opacity: 0.6;
            z-index: 0;
        }

        /* Bring the box above the overlay */
        .login-box {
            position: relative;
            z-index: 1;
        }

        /* Box styling */
        .login-box-body {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 30px 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Logo */
        .login-logo img {
            max-width: 150px;
            margin: 0 auto 20px;
            display: block;
        }

        /* Headings */
        .login-box-msg {
            color: #134169;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .login-box-body .text-center {
            color: #333;
            font-size: 16px;
        }

        /* Inputs */
        .form-control {
            border-radius: 6px;
            border: 1px solid #ccc;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #134169;
            box-shadow: none;
        }

        /* Buttons */
        .btn-primary {
            background-color: #134169;
            border-color: #0f3150;
            border-radius: 6px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #0f3150;
        }

        /* Links */
        a {
            color: #134169;
            font-weight: 500;
        }
        a:hover {
            text-decoration: underline;
        }

        /* Feedback icons */
        .form-control-feedback {
            color: #888;
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <img src="{{ url('public/assets/images/logo.png') }}" alt="Logo">
        </div>
        <div class="login-box-body">
            <p class="text-center">Welcome To</p>
            <p class="login-box-msg">{{ env('APP_NAME') }}</p>
            <hr>
            <p class="text-center">Login</p>

            <form action="{{ admin_url('auth/login') }}" method="post">
                <div class="form-group has-feedback {!! !$errors->has('username') ?: 'has-error' !!}">
                    @if ($errors->has('username'))
                        @foreach ($errors->get('username') as $message)
                            <label class="control-label" for="inputError">
                                <i class="fa fa-times-circle-o"></i>{{ $message }}
                            </label><br>
                        @endforeach
                    @endif
                    <input type="text" class="form-control" placeholder="{{ trans('admin.username') }}"
                        name="username" value="{{ old('username') }}">
                    <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                </div>

                <div class="form-group has-feedback {!! !$errors->has('password') ?: 'has-error' !!}">
                    @if ($errors->has('password'))
                        @foreach ($errors->get('password') as $message)
                            <label class="control-label" for="inputError">
                                <i class="fa fa-times-circle-o"></i>{{ $message }}
                            </label><br>
                        @endforeach
                    @endif
                    <input type="password" class="form-control" placeholder="{{ trans('admin.password') }}"
                        name="password">
                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                </div>

                <div class="row">
                    <div class="col-xs-8">
                        <a href="{{ url('auth/register') }}">Create Account</a>
                        <input type="hidden" name="remember" value="1">
                    </div>
                    <div class="col-xs-4">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="btn btn-primary btn-block btn-flat">
                            {{ trans('admin.login') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/plugins/jQuery/jQuery-2.1.4.min.js') }}"></script>
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/plugins/iCheck/icheck.min.js') }}"></script>
    <script>
        $(function() {
            $('input').iCheck({
                checkboxClass: 'icheckbox_square-blue',
                radioClass: 'iradio_square-blue',
                increaseArea: '20%'
            });
        });
    </script>
</body>

</html>
