<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Muni University EHRMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --muni-maroon: #800000;
            --muni-dark: #600000;
            --muni-darker: #400000;
            --muni-light: #a00000;
            --gold-accent: #D4AF37;
            --text-primary: #1a1a2e;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --bg-white: #ffffff;
            --bg-light: #f7fafc;
            --bg-lighter: #fafbfc;
            --border-color: #e2e8f0;
            --error-red: #dc3545;
            --success-green: #28a745;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--muni-maroon) 0%, var(--muni-dark) 50%, var(--muni-darker) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Animated Background Pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(212, 175, 55, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(128, 0, 0, 0.1) 0%, transparent 70%);
            pointer-events: none;
            z-index: 1;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1100px;
            padding: 20px;
            z-index: 2;
            position: relative;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg-white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            min-height: 650px;
        }

        /* Left Panel - Branding */
        .branding-panel {
            background: linear-gradient(135deg, var(--muni-maroon) 0%, var(--muni-dark) 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .branding-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .university-logo {
            width: 140px;
            height: 140px;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .university-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.3));
            background: white;
            border-radius: 50%;
            padding: 10px;
            border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .branding-content {
            position: relative;
            z-index: 2;
        }

        .university-name {
            font-size: 32px;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .system-tagline {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
            margin-bottom: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .system-description {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 300;
            line-height: 1.6;
            max-width: 360px;
            margin: 20px auto 0;
        }

        .feature-badges {
            display: flex;
            gap: 12px;
            margin-top: 40px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Right Panel - Login Form */
        .login-panel {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--bg-white);
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            font-size: 15px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            z-index: 2;
            transition: color 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
            background: var(--bg-lighter);
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-control:hover {
            border-color: var(--muni-light);
            background: var(--bg-white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--muni-maroon);
            background: var(--bg-white);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08);
        }

        .form-control:focus + .input-icon {
            color: var(--muni-maroon);
        }

        .has-error .form-control {
            border-color: var(--error-red);
            background: #fff5f5;
        }

        .has-error .input-icon {
            color: var(--error-red);
        }

        .help-block {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--error-red);
            font-size: 13px;
            margin-top: 8px;
            font-weight: 500;
        }

        .help-block i {
            font-size: 14px;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--muni-maroon);
        }

        .forgot-password {
            font-size: 14px;
            color: var(--muni-maroon);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .forgot-password:hover {
            color: var(--muni-dark);
            text-decoration: underline;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--muni-maroon) 0%, var(--muni-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.25);
            letter-spacing: 0.3px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--muni-dark) 0%, var(--muni-darker) 100%);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.35);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.25);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            padding: 0 16px;
            font-weight: 500;
        }

        .support-info {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .support-info p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .support-info a {
            color: var(--muni-maroon);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .support-info a:hover {
            text-decoration: underline;
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            padding: 0 20px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 480px;
                margin: 0 auto;
            }

            .branding-panel {
                display: none;
            }

            .login-panel {
                padding: 40px 30px;
            }

            .university-name {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .login-wrapper {
                padding: 12px;
            }

            .login-panel {
                padding: 32px 24px;
            }

            .login-header h1 {
                font-size: 26px;
            }

            .form-control {
                padding: 12px 16px 12px 42px;
                font-size: 14px;
            }

            .btn-submit {
                padding: 14px;
                font-size: 15px;
            }
        }

        /* Loading Animation */
        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-submit.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spinner 0.6s linear infinite;
        }

        @keyframes spinner {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Left Panel - University Branding -->
            <div class="branding-panel">
                <div class="university-logo">
                    @php
                        $logo = url('assets/images/logo.jpg'); 
                    @endphp
                    <img src="{{ $logo }}" alt="Muni University Logo">
                </div>
                <div class="branding-content">
                    <h2 class="university-name">
                        Mountains of the Moon<br>University
                    </h2>
                    <p class="system-tagline">EHRMS Portal</p>
                    <p class="system-description">
                        Electronic Human Resource Management System - 
                        Streamlining workforce management and administrative excellence
                    </p>
                    <div class="feature-badges">
                        <span class="badge"><i class="fas fa-users"></i> Employee Management</span>
                        <span class="badge"><i class="fas fa-clock"></i> Attendance Tracking</span>
                        <span class="badge"><i class="fas fa-calendar-check"></i> Leave Management</span>
                        <span class="badge"><i class="fas fa-chart-line"></i> Analytics</span>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Login Form -->
            <div class="login-panel">
                <div class="login-header">
                    <h1>Welcome Back</h1>
                    <p>Sign in to access the EHRMS portal</p>
                </div>

                <form action="{{ url('auth/login') }}" method="post" class="login-form" autocomplete="off">
                    @csrf

                    <div class="form-group {{ $errors->has('username') ? 'has-error' : '' }}">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                placeholder="Enter your username"
                                value="{{ old('username') }}"
                                required
                                autofocus>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                        @if ($errors->has('username'))
                            @foreach ($errors->get('username') as $message)
                                <span class="help-block">
                                    <i class="fas fa-exclamation-circle"></i> {{ $message }}
                                </span>
                            @endforeach
                        @endif
                    </div>

                    <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter your password"
                                required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                        @if ($errors->has('password'))
                            @foreach ($errors->get('password') as $message)
                                <span class="help-block">
                                    <i class="fas fa-exclamation-circle"></i> {{ $message }}
                                </span>
                            @endforeach
                        @endif
                    </div>

                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" value="1" checked>
                            <span>Remember me</span>
                        </label>
                        {{-- <a href="#" class="forgot-password">Forgot password?</a> --}}
                    </div>

                    <button type="submit" class="btn-submit">
                        Sign In
                    </button>

                    <div class="divider">
                        <span>Secure Login</span>
                    </div>

                    <div class="support-info">
                        <p>Need help accessing your account?</p>
                        <a href="mailto:ict@muni.ac.ug"><i class="fas fa-envelope"></i> Contact IT Support</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="footer-text">
            &copy; {{ date('Y') }} Mountains of the Moon University. All Rights Reserved. | Powered by EHRMS
        </div>
    </div>

    <script>
        // Add loading state to button on form submit
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-submit');
            btn.classList.add('loading');
            btn.textContent = 'Signing In...';
        });

        // Input focus animation
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>
