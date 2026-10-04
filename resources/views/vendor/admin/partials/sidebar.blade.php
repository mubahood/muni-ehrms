@php
    /** @var \App\Models\User $me */
    $me = Admin::user();
    $path = request()->path() === '/' ? '' : request()->path();
    $prefix = trim((string) config('admin.route.prefix'), '/');
    if ($prefix !== '' && strpos($path, $prefix) === 0) {
        $path = trim(substr($path, strlen($prefix)), '/');
    }
@endphp
<aside class="main-sidebar">
    <section class="sidebar">
        <ul class="side-nav" id="side-nav" data-base="{{ rtrim(parse_url(admin_url('/'), PHP_URL_PATH) ?: '', '/') }}">
            @foreach (\App\Support\Navigation::for($me) as $group)
                <li class="side-group">{{ $group['title'] }}</li>
                @foreach ($group['items'] as $item)
                    <li class="@if (\App\Support\Navigation::isActive($item['uri'], $path)) active @endif" data-uri="{{ trim($item['uri'], '/') }}">
                        <a href="{{ admin_url($item['uri']) }}">
                            <i class="fa {{ $item['icon'] }}"></i>
                            <span>{{ $item['title'] }}</span>
                            @if (!empty($item['count']))
                                <span class="count" title="{{ $item['count'] }} waiting for you">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            @endforeach
        </ul>
        <div class="side-scope">
            <b>You see</b>
            {{ \App\Services\Scope::label($me) }}
        </div>
    </section>
</aside>
<script>
    // Pages load through PJAX, so the sidebar is not redrawn: mark the open page here.
    (function () {
        function mark() {
            var nav = document.getElementById('side-nav');
            if (!nav) { return; }
            var base = nav.getAttribute('data-base') || '';
            var path = window.location.pathname.replace(base, '').replace(/^\/+|\/+$/g, '');
            var best = null;
            nav.querySelectorAll('li[data-uri]').forEach(function (li) {
                li.classList.remove('active');
                var uri = li.getAttribute('data-uri');
                var hit = uri === '' ? path === '' : (path === uri || path.indexOf(uri + '/') === 0);
                if (hit && (!best || uri.length > best.getAttribute('data-uri').length)) { best = li; }
            });
            if (best) { best.classList.add('active'); }
        }
        $(document).on('pjax:end', mark);
        mark();
    })();
</script>
