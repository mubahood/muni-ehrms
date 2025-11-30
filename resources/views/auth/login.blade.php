<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Faras Attendance Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --primary-green: #00BF63;
            --dark-green: #00994f;
            --light-gray: #f4f7f6;
            --text-dark: #222b45;
            --text-light: #7b8a99;
            --border-color: #e0e6ed;
            --error-red: #e74c3c;
            --white: #fff;
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            --gradient: linear-gradient(135deg, #00BF63 0%, #00994f 100%);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-dark);
            position: relative;
        }

        .background-blur {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            pointer-events: none;
            background: url('https://www.transparenttextures.com/patterns/diagmonds-light.png'), rgba(255,255,255,0.08);
            mix-blend-mode: lighten;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 24px;
            z-index: 1;
        }

        .login-box {
            background: var(--white);
            border-radius: 22px;
            padding: 48px 36px 36px 36px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-box::before {
            content: '';
            position: absolute;
            top: -60px; left: -60px;
            width: 180px; height: 180px;
            background: radial-gradient(circle, #00BF6340 60%, transparent 100%);
            z-index: 0;
        }

        .login-logo img {
            max-width: 90px;
            margin-bottom: 18px;
            border-radius: 50%;
            box-shadow: 0 4px 16px rgba(0,191,99,0.08);
            border: 3px solid #e0e6ed;
            background: #fff;
        }

        .login-title h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--primary-green);
        }

        .login-title p {
            margin: 0 0 32px 0;
            color: var(--text-light);
            font-size: 15px;
        }

        .form-group {
            position: relative;
            margin-bottom: 22px;
            text-align: left;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            background: #f8fafb;
            color: var(--text-dark);
            transition: border-color 0.3s, box-shadow 0.3s;
            font-weight: 500;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 191, 99, 0.13);
            background: #fff;
        }

        .form-control-feedback {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: var(--border-color);
            font-size: 18px;
            transition: color 0.3s;
            z-index: 2;
        }

        .form-control:focus + .form-control-feedback {
            color: var(--primary-green);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: var(--gradient);
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,191,99,0.08);
            transition: background 0.3s, transform 0.2s, box-shadow 0.2s;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover, .btn-submit:focus {
            background: linear-gradient(135deg, #00994f 0%, #00BF63 100%);
            box-shadow: 0 4px 16px rgba(0,191,99,0.13);
            transform: translateY(-2px) scale(1.01);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .help-block {
            color: var(--error-red);
            font-size: 13px;
            margin-top: 6px;
            display: block;
            font-weight: 500;
        }

        .has-error .form-control {
            border-color: var(--error-red);
            background: #fff0f0;
        }

        .has-error .form-control-feedback {
            color: var(--error-red);
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 15px;
            color: var(--primary-green);
            letter-spacing: 0.3px;
            font-weight: 600;
            background: #f8fafb;
            border-radius: 10px;
            padding: 12px 0;
            box-shadow: 0 2px 8px rgba(0,191,99,0.06);
        }

        @media (max-width: 600px) {
            .login-box {
                padding: 32px 12px 24px 12px;
            }
            .login-container {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="background-blur"></div>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                @php
                    $logo = url('assets/images/logo.jpg'); 
                @endphp
                <img src="{{ $logo }}" alt="Faras Uganda Logo">
            </div>
            <div class="login-title">
                <h2>Attendance Portal</h2>
                <p>Welcome back! Please sign in to continue</p>
            </div>

            <form action="{{ url('auth/login') }}" method="post" autocomplete="off">
                @csrf

                <div class="form-group has-feedback {{ $errors->has('username') ? 'has-error' : '' }}">
                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="{{ trans('admin.username') }}"
                        value="{{ old('username') }}"
                        required
                        autofocus>
                    <span class="fa fa-user form-control-feedback"></span>
                    @if ($errors->has('username'))
                        @foreach ($errors->get('username') as $message)
                            <span class="help-block">
                                <i class="fa fa-times-circle-o"></i> {{ $message }}
                            </span>
                        @endforeach
                    @endif
                </div>

                <div class="form-group has-feedback {{ $errors->has('password') ? 'has-error' : '' }}">
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="{{ trans('admin.password') }}"
                        required>
                    <span class="fa fa-lock form-control-feedback"></span>
                    @if ($errors->has('password'))
                        @foreach ($errors->get('password') as $message)
                            <span class="help-block">
                                <i class="fa fa-times-circle-o"></i> {{ $message }}
                            </span>
                        @endforeach
                    @endif
                </div>

                <input type="hidden" name="remember" value="1">

                <div class="form-group" style="margin-top: 32px;">
                    <button type="submit" class="btn-submit">
                        {{ trans('admin.login') }}
                    </button>
                </div>
            </form>
        </div>
        <div class="login-footer">
            &copy; {{ date('Y') }} Faras Uganda. All Rights Reserved.
        </div>
    </div>
</body>
</html>
