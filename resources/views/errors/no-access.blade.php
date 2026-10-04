<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No access | Muni University EHRMS</title>
    <link rel="icon" type="image/png" href="{{ url('assets/brand/favicon-32.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --maroon: #800000; --ink: #1a1a1a; --muted: #767676; --line: #d8d8d8; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: var(--ink); font: 15px/1.55 'Inter', -apple-system, 'Segoe UI', Arial, sans-serif; }
        .topbar { height: 4px; background: var(--maroon); }
        .wrap { max-width: 560px; margin: 0 auto; padding: 72px 24px; }
        .mark { display: flex; align-items: center; gap: 14px; margin-bottom: 40px; }
        .mark img { width: 52px; height: 56px; object-fit: contain; }
        .mark b { display: block; font-size: 16px; }
        .mark span { font-size: 11px; letter-spacing: .13em; text-transform: uppercase; color: var(--maroon); font-weight: 600; }
        .code { font-size: 12px; font-weight: 700; letter-spacing: .12em; color: var(--maroon); text-transform: uppercase; }
        h1 { font-size: 26px; margin: 6px 0 12px; letter-spacing: -.01em; }
        p { color: #4a4a4a; margin: 0 0 12px; }
        .who { border: 1px solid var(--line); padding: 12px 14px; margin: 24px 0; font-size: 14px; color: var(--muted); }
        .who strong { color: var(--ink); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 11px 18px; font-size: 13px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; text-decoration: none; border: 1px solid var(--maroon); }
        .btn.primary { background: var(--maroon); color: #fff; }
        .btn.ghost { color: var(--maroon); background: #fff; }
    </style>
</head>
<body>
<div class="topbar"></div>
<div class="wrap">
    <div class="mark">
        <img src="{{ url('assets/brand/muni-crest.png') }}" alt="Muni University">
        <div><b>Muni University</b><span>EHRMS Portal</span></div>
    </div>
    <div class="code">Error 403</div>
    <h1>This page is not available to you</h1>
    <p>Your role does not include access to this part of the system. If you need it for your work, ask the System Administrator to review your role.</p>
    <div class="who">Signed in as <strong>{{ $user->displayName() }}</strong> · {{ $user->roleLabel() }}</div>
    <div class="actions">
        <a class="btn primary" href="{{ admin_url('/') }}">Go to my dashboard</a>
        <a class="btn ghost" href="{{ admin_url('auth/logout') }}">Sign out</a>
    </div>
</div>
</body>
</html>
