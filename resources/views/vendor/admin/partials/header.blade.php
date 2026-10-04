@php
    /** @var \App\Models\User $me */
    $me = Admin::user();
    $unread = $me->unreadNotifications()->count();
    $recent = $me->notifications()->latest()->limit(8)->get();
@endphp
<header class="main-header">
    <a href="{{ admin_url('/') }}" class="logo">
        <img src="{{ url('assets/brand/muni-crest.png') }}" alt="Muni University">
        <span class="logo-text"><b>Muni University</b><span>EHRMS Portal</span></span>
    </a>

    <nav class="navbar navbar-static-top" role="navigation">
        <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button" aria-label="Show or hide the menu">
            <span class="sr-only">Toggle navigation</span>
        </a>

        <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
                @if (Admin::user() && Admin::user()->isDemo())
                    <li><span class="demo-badge" title="You are exploring the demonstration university. No real staff or records are shown, and university-wide settings are read-only.">
                        <i class="fa fa-flask"></i><span class="hidden-xs"> Demo account</span></span></li>
                    @if (session()->has('ehr_demo_return'))
                        <li><form method="post" action="{{ admin_url('demo/leave') }}" class="demo-back">@csrf
                            <button type="submit" title="Leave the demo and return to your own account"><i class="fa fa-sign-out"></i><span class="hidden-xs"> Back to my account</span></button></form></li>
                    @endif
                @endif
                <li class="hidden-xs"><span class="header-clock" id="header-clock" aria-live="off"></span></li>

                <li class="dropdown" id="bell-menu">
                    <a href="#" class="dropdown-toggle bell" data-toggle="dropdown" aria-label="Notifications">
                        <i class="fa fa-bell-o"></i>
                        <span class="bell-count" id="bell-count" @if (!$unread) style="display:none" @endif>{{ $unread }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right menu-panel">
                        <div class="menu-head">
                            <span>Notifications</span>
                            @if ($unread)
                                <a href="{{ admin_url('notifications/read-all') }}" data-method="post" class="js-read-all">Mark all as read</a>
                            @endif
                        </div>
                        <div class="menu-list">
                            @forelse ($recent as $note)
                                <a class="note @if (!$note->read_at) unread @endif" href="{{ admin_url('notifications/' . $note->id) }}">
                                    <b>{{ $note->data['title'] ?? 'Notification' }}</b>
                                    <span>{{ \Illuminate\Support\Str::limit($note->data['body'] ?? '', 140) }}</span>
                                    <small>{{ $note->created_at->diffForHumans() }}</small>
                                </a>
                            @empty
                                <div class="menu-empty">Nothing new. Leave requests and decisions will appear here.</div>
                            @endforelse
                        </div>
                        <div class="menu-head" style="border-top:1px solid var(--line-soft);border-bottom:0">
                            <a href="{{ admin_url('notifications') }}">See all notifications</a>
                        </div>
                    </div>
                </li>

                <li class="dropdown user user-menu">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <span class="header-who"><b>{{ $me->displayName() }}</b><span>{{ $me->roleLabel() }}</span></span>
                        <span class="avatar-initials">{{ $me->initials() }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right menu-panel" style="width:290px">
                        <div class="user-card">
                            <span class="avatar-initials" style="width:42px;height:42px;font-size:14px">{{ $me->initials() }}</span>
                            <div>
                                <b>{{ $me->displayName() }}</b>
                                <span>{{ $me->roleLabel() }}@if ($me->department) · {{ $me->department->name }}@endif</span>
                            </div>
                        </div>
                        <div class="menu-links">
                            <a href="{{ admin_url('me') }}"><i class="fa fa-calendar-check-o fa-fw"></i> My attendance</a>
                            <a href="{{ admin_url('my-leave') }}"><i class="fa fa-plane fa-fw"></i> My leave</a>
                            <a href="{{ admin_url('auth/setting') }}"><i class="fa fa-user fa-fw"></i> Update my profile</a>
                            <a href="{{ admin_url('auth/logout') }}" class="danger no-pjax"><i class="fa fa-sign-out fa-fw"></i> Sign out</a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</header>
<script>
    // Keep the bell current on long-open pages: check every minute.
    (function () {
        var url = @json(admin_url('notifications/summary'));
        setInterval(function () {
            if (document.hidden) { return; }
            $.getJSON(url).done(function (data) {
                var badge = document.getElementById('bell-count');
                if (!badge) { return; }
                badge.textContent = data.unread;
                badge.style.display = data.unread ? '' : 'none';
            });
        }, 60000);
        $(document).on('click', '.js-read-all', function (e) {
            e.preventDefault();
            $.post(this.href, { _token: LA.token }).done(function () { window.location.reload(); });
        });
    })();
</script>
