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
        /* Full-page background image */
        html,
        body {
            height: 100%;
            margin: 0;
        }

        body.login-page {
            background: url('{{ asset('assets/images/syama-1.jpg') }}') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Center the login box */
        .login-box {
            width: 100%;
            max-width: 380px;
            margin: auto;
        }

        /* White “card” styling */
        .login-box-body {
            background: rgba(255, 255, 255) !important;
            border-radius: 15px !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            padding: 30px 25px;
            border: none;
        }

        /* Logo */
        .login-box-body .login-logo img {
            display: block;
            margin: 0 auto 15px;
            max-width: 140px;
        }

        /* App name */
        .login-box-msg {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Form controls */
        .login-box-body .form-control {
            border-radius: 4px;
            border: 1px solid #ccc;
            padding: 10px;
            height: 45px;
            transition: border-color 0.2s;
        }

        .login-box-body .form-control:focus {
            border-color: #0f3150;
            box-shadow: none;
        }

        .login-box-body .glyphicon {
            color: #888;
        }

        .has-error .form-control {
            border-color: #a94442;
        }

        .has-error .control-label {
            color: #a94442;
            margin-bottom: 5px;
            display: block;
        }

        /* Login button */
        .login-box-body .btn-primary {
            background-color: #134169;
            border: none;
            border-radius: 4px;
            padding: 12px;
            width: 100%;
            font-weight: 600;
            text-transform: uppercase;
        }

        .login-box-body .btn-primary:hover {
            background-color: #0f3150;
        }

        /* Footer */
        .footer {
            margin-top: 25px;
            text-align: center;
        }

        .footer p {
            font-size: 12px;
            color: #0f3150;
            font-weight: 500;
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-box-body">
            <div class="login-logo">
                <img style="max-width: 200px; height: auto;" src="{{ url('public/assets/images/somisy.png') }}"
                    alt="Logo">
            </div>

            <hr>

            <p class="login-box-msg text-uppercase p-0 m-0">
                {{ env('APP_NAME') }}
            </p>


            @yield('content')


            <div class="footer">
                <p>© {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- jQuery 2.1.4 -->
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/plugins/jQuery/jQuery-2.1.4.min.js') }}"></script>
    <!-- Bootstrap 3.3.5 -->
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/bootstrap/js/bootstrap.min.js') }}"></script>
    <!-- iCheck -->
    <script src="{{ admin_asset('vendor/laravel-admin/AdminLTE/plugins/iCheck/icheck.min.js') }}"></script>
    <script>
        $(function() {
            $('input').iCheck({
                checkboxClass: 'icheckbox_square-blue',
                radioClass: 'iradio_square-blue',
                increaseArea: '20%' // optional
            });
        });
    </script>
</body>

</html>
