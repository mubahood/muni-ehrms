<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#800000">
    <title>Sign in | Muni University EHRMS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('assets/brand/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ url('assets/brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #800000; --maroon-800: #5a0000; --maroon-tint: #f8eded;
            --ink: #161616; --ink-2: #3f3f3f; --muted: #727272; --line: #dedede; --line-soft: #ececec;
            --danger: #b42318; --ease: cubic-bezier(.2, .7, .2, 1);
        }
        *, *::before, *::after { box-sizing: border-box; border-radius: 0; }
        html, body { height: 100%; }
        body {
            margin: 0; color: var(--ink); background: #fff; font: 13px/1.45 'Inter', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased; display: grid; grid-template-columns: minmax(360px, 44%) 1fr; min-height: 100vh;
        }

        /* ---------------------------------------------------- identity half */
        .brand {
            background: var(--maroon); color: #fff; display: flex; flex-direction: column; justify-content: space-between;
            padding: 36px 44px 28px; position: relative; overflow: hidden;
        }
        .brand::after { /* one quiet diagonal for depth, nothing decorative */
            content: ""; position: absolute; right: -20%; bottom: -30%; width: 70%; height: 80%;
            background: var(--maroon-800); transform: rotate(-18deg); opacity: .55; pointer-events: none;
        }
        .brand > * { position: relative; z-index: 1; }
        .mark { display: flex; align-items: center; gap: 12px; }
        .mark img { width: 46px; height: 50px; object-fit: contain; background: #fff; padding: 3px; }
        .mark b { display: block; font-size: 15px; font-weight: 800; letter-spacing: .01em; }
        .mark span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: rgba(255, 255, 255, .7); margin-top: 2px; }
        .pitch h1 { margin: 0; font-size: 30px; line-height: 1.12; font-weight: 800; letter-spacing: -.02em; max-width: 13em; }
        .pitch p { margin: 12px 0 0; font-size: 14px; color: rgba(255, 255, 255, .78); max-width: 27em; }
        .caps { list-style: none; margin: 22px 0 0; padding: 0; display: grid; gap: 7px; }
        .caps li { display: flex; gap: 10px; align-items: center; font-size: 13px; font-weight: 500; color: rgba(255, 255, 255, .92); }
        .caps li::before { content: ""; width: 14px; height: 2px; background: #fff; flex: none; }
        .legal { font-size: 11.5px; color: rgba(255, 255, 255, .6); display: flex; justify-content: space-between; gap: 12px; }

        /* ------------------------------------------------------- form half */
        main { display: flex; align-items: center; justify-content: center; padding: 28px 24px; }
        .sheet { width: 100%; max-width: 344px; }
        .sheet h2 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.015em; }
        .sheet .sub { margin: 3px 0 18px; color: var(--muted); font-size: 13px; }
        .alert { border-left: 3px solid var(--danger); background: #fff6f5; color: var(--danger); padding: 8px 10px; font-size: 12.5px; font-weight: 600; margin-bottom: 12px; }
        .field { margin-bottom: 11px; }
        .field label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; }
        .control { position: relative; }
        .control input {
            width: 100%; height: 38px; padding: 0 11px; font: inherit; font-size: 13.5px; font-weight: 600; color: var(--ink);
            background: #fff; border: 1px solid #cfcfcf; outline: none; transition: border-color .12s var(--ease), box-shadow .12s var(--ease);
        }
        .control input::placeholder { color: #a5a5a5; font-weight: 400; }
        .control input:hover { border-color: #b3b3b3; }
        .control input:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(128, 0, 0, .12); }
        .field.invalid input { border-color: var(--danger); }
        .control .reveal {
            position: absolute; right: 1px; top: 1px; bottom: 1px; width: 58px; border: 0; border-left: 1px solid var(--line-soft);
            background: #fff; color: var(--muted); font: inherit; font-size: 11.5px; font-weight: 700; cursor: pointer;
        }
        .control .reveal:hover { color: var(--maroon); }
        .control.with-reveal input { padding-right: 66px; }
        .row { display: flex; align-items: center; justify-content: space-between; margin: 2px 0 14px; }
        .remember { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--ink-2); cursor: pointer; user-select: none; }
        .remember input { width: 15px; height: 15px; margin: 0; accent-color: var(--maroon); }
        .row a { font-size: 12.5px; color: var(--maroon); font-weight: 600; text-decoration: none; }
        .row a:hover { text-decoration: underline; }
        .submit {
            width: 100%; height: 40px; border: 0; background: var(--maroon); color: #fff; font: inherit; font-size: 13px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase; cursor: pointer; position: relative; transition: background .12s var(--ease), transform .08s;
        }
        .submit:hover { background: var(--maroon-800); }
        .submit:active { transform: translateY(1px); }
        .submit:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }
        .submit.busy { color: transparent; pointer-events: none; }
        .submit.busy::after {
            content: ""; position: absolute; left: 50%; top: 50%; width: 16px; height: 16px; margin: -8px 0 0 -8px;
            border: 2px solid #fff; border-color: #fff #fff transparent transparent; border-radius: 50%; animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ------------------------------------------------- demo accounts */
        .try-demo { margin-top: 18px; width: 100%; display: flex; align-items: center; gap: 12px; padding: 11px 14px; background: #fff; cursor: pointer;
            border: 1px dashed #c9a3a3; color: var(--ink); text-align: left; font: inherit; transition: background .14s var(--ease), border-color .14s var(--ease); }
        .try-demo:hover { background: var(--maroon-tint); border-color: var(--maroon); border-style: solid; }
        .try-demo:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }
        .try-demo .ic { flex: 0 0 34px; height: 34px; display: grid; place-items: center; background: var(--maroon); color: #fff; font-weight: 800; font-size: 15px; }
        .try-demo b { display: block; font-size: 13px; }
        .try-demo span { display: block; font-size: 11.5px; color: var(--muted); }
        .try-demo .go { margin-left: auto; color: var(--maroon); font-weight: 800; font-size: 18px; }

        .dm { position: fixed; inset: 0; z-index: 50; display: none; align-items: center; justify-content: center; padding: 24px; background: rgba(22, 22, 22, .55); }
        .dm.open { display: flex; animation: dm-fade .16s var(--ease); }
        .dm-box { background: #fff; width: min(760px, 100%); max-height: calc(100vh - 48px); display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(0, 0, 0, .3);
            border-top: 4px solid var(--maroon); animation: dm-rise .2s var(--ease); }
        .dm-head { padding: 16px 20px 12px; border-bottom: 1px solid var(--line-soft); display: flex; gap: 12px; align-items: flex-start; }
        .dm-head h3 { margin: 0; font-size: 16px; font-weight: 800; }
        .dm-head p { margin: 3px 0 0; font-size: 12px; color: var(--muted); line-height: 1.5; }
        .dm-head code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--maroon); font-weight: 700; background: var(--maroon-tint); padding: 0 4px; }
        .dm-x { margin-left: auto; border: 0; background: none; font-size: 22px; line-height: 1; color: var(--muted); cursor: pointer; padding: 0 2px; }
        .dm-x:hover { color: var(--maroon); }
        .dm-body { overflow-y: auto; padding: 4px 20px 16px; }
        .dm-group { margin-top: 12px; }
        .dm-group > b { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
        .dm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
        .dm-acc { display: flex; gap: 10px; align-items: center; text-align: left; width: 100%; padding: 9px 10px; background: #fff; border: 1px solid var(--line); cursor: pointer; font: inherit; color: var(--ink);
            transition: border-color .12s, background .12s, transform .12s var(--ease); }
        .dm-acc:hover, .dm-acc:focus-visible { border-color: var(--maroon); background: var(--maroon-tint); outline: none; transform: translateY(-1px); }
        .dm-acc .av { flex: 0 0 34px; height: 34px; display: grid; place-items: center; font-size: 11.5px; font-weight: 800; color: var(--maroon); background: var(--maroon-tint); border: 1px solid #ead0d0; }
        .dm-acc:hover .av { background: var(--maroon); color: #fff; border-color: var(--maroon); }
        .dm-acc .t { min-width: 0; }
        .dm-acc .n { display: block; font-weight: 700; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dm-acc .p { display: block; font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dm-acc .u { display: block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10.5px; color: var(--maroon); margin-top: 1px; }
        .dm-acc.busy { pointer-events: none; opacity: .7; }
        .dm-foot { padding: 10px 20px; border-top: 1px solid var(--line-soft); background: #fafafa; font-size: 11.5px; color: var(--muted); }
        @keyframes dm-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes dm-rise { from { transform: translateY(10px); opacity: .6; } to { transform: none; opacity: 1; } }
        @media (max-width: 640px) {
            .dm { padding: 0; align-items: stretch; }
            .dm-box { max-height: 100vh; width: 100%; border-top-width: 3px; }
            .dm-grid { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>
    <aside class="brand">
        <div class="mark">
            <img src="{{ url('assets/brand/muni-crest.png') }}" alt="Muni University crest">
            <div><b>Muni University</b><span>EHRMS Portal</span></div>
        </div>
        <div class="pitch">
            <h1>Electronic Human Resource Management</h1>
            <p>Attendance from the face-recognition terminals, leave from application to approval, and the reports that go with them.</p>
            <ul class="caps">
                <li>Attendance, punctuality and hours, day by day</li>
                <li>Leave applied for, approved and tracked online</li>
                <li>Reports for every department and faculty</li>
            </ul>
        </div>
        <div class="legal"><span>&copy; {{ date('Y') }} Muni University</span><span>P.O. Box 725 Arua, Uganda</span></div>
    </aside>

    <main>
        <div class="sheet">
            <h2>Sign in</h2>
            <p class="sub">Use the credentials issued to you by the ICT Office.</p>

            <form action="{{ url('auth/login') }}" method="post" id="login-form" novalidate>
                @csrf
                @if ($errors->any())
                    <div class="alert" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="field {{ $errors->has('username') ? 'invalid' : '' }}">
                    <label for="username">Username or e-mail</label>
                    <div class="control">
                        <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="e.g. j.okello"
                               autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                    </div>
                </div>
                <div class="field {{ $errors->has('password') ? 'invalid' : '' }}">
                    <label for="password">Password</label>
                    <div class="control with-reveal">
                        <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="reveal" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <div class="row">
                    <label class="remember"><input type="checkbox" name="remember" value="1" checked> Keep me signed in</label>
                    <a href="mailto:ict@muni.ac.ug?subject=EHRMS%20password%20reset">Forgot password?</a>
                </div>
                <button type="submit" class="submit">Sign in</button>
            </form>

            @if (!empty($demoAccounts))
                <button type="button" class="try-demo" id="try-demo" aria-haspopup="dialog" aria-controls="demo-modal">
                    <span class="ic" aria-hidden="true">D</span>
                    <span><b>Try a demo account</b><span>{{ collect($demoAccounts)->flatten(1)->count() }} accounts, one for every role, with three months of data</span></span>
                    <span class="go" aria-hidden="true">&rsaquo;</span>
                </button>
            @endif

            <p class="help">Trouble signing in? Contact <a href="mailto:ict@muni.ac.ug">ict@muni.ac.ug</a>.</p>
        </div>
    </main>

    @if (!empty($demoAccounts))
        <div class="dm" id="demo-modal" role="dialog" aria-modal="true" aria-labelledby="dm-title" hidden>
            <div class="dm-box">
                <div class="dm-head">
                    <div>
                        <h3 id="dm-title">Choose a demo account</h3>
                        <p>A demonstration university with its own staff, attendance and leave: no real person or record is shown.
                            Click an account to sign in. Password for all: <code>{{ $demoPassword }}</code></p>
                    </div>
                    <button type="button" class="dm-x" data-close aria-label="Close">&times;</button>
                </div>
                <div class="dm-body">
                    @foreach ($demoAccounts as $group => $accounts)
                        <div class="dm-group">
                            <b>{{ $group }}</b>
                            <div class="dm-grid">
                                @foreach ($accounts as $a)
                                    <button type="button" class="dm-acc" data-username="{{ $a['username'] }}">
                                        <span class="av" aria-hidden="true">{{ $a['initials'] }}</span>
                                        <span class="t">
                                            <span class="n">{{ $a['name'] }}</span>
                                            <span class="p">{{ $a['position'] }}{{ $a['department'] && strpos($a['position'], $a['department']) === false ? ' · ' . $a['department'] : '' }}</span>
                                            <span class="u">{{ $a['username'] }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="dm-foot">Demo accounts can explore everything their role allows, but cannot change university-wide settings. The demo is reset regularly.</div>
            </div>
        </div>
    @endif

    <script>
        (function () {
            var form = document.getElementById('login-form');
            var username = form.querySelector('[name="username"]');
            var password = form.querySelector('[name="password"]');
            var submit = form.querySelector('.submit');
            var reveal = form.querySelector('.reveal');

            reveal.addEventListener('click', function () {
                var show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                reveal.textContent = show ? 'Hide' : 'Show';
                reveal.setAttribute('aria-pressed', String(show));
                password.focus();
            });

            // Demo accounts: a window listing them by role; one click signs in.
            var modal = document.getElementById('demo-modal');
            var opener = document.getElementById('try-demo');
            if (modal && opener) {
                var open = function () {
                    modal.hidden = false;
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                    var first = modal.querySelector('.dm-acc');
                    if (first) { first.focus(); }
                };
                var close = function () {
                    modal.classList.remove('open');
                    modal.hidden = true;
                    document.body.style.overflow = '';
                    opener.focus();
                };
                opener.addEventListener('click', open);
                modal.addEventListener('click', function (e) {
                    if (e.target === modal || e.target.closest('[data-close]')) { close(); }
                    var acc = e.target.closest('.dm-acc');
                    if (!acc) { return; }
                    acc.classList.add('busy');
                    acc.querySelector('.u').textContent = 'Signing in…';
                    username.value = acc.dataset.username;
                    password.value = {!! json_encode($demoPassword ?? '') !!};
                    submit.classList.add('busy');
                    form.submit();
                });
                document.addEventListener('keydown', function (e) {
                    if (modal.hidden) { return; }
                    if (e.key === 'Escape') { close(); }
                    if (e.key === 'Tab') {
                        var f = modal.querySelectorAll('button');
                        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
                    }
                });
            }

            form.addEventListener('submit', function (e) {
                if (!username.value.trim() || !password.value) {
                    e.preventDefault();
                    (username.value.trim() ? password : username).focus();
                    return;
                }
                submit.classList.add('busy');
            });
        })();
    </script>
</body>
</html>
