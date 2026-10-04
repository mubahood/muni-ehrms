{{-- laravel-admin page shell, overridden for the Muni University house style.
     Changes from the package: page title and favicon, pinch-zoom allowed on phones. --}}
<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="renderer" content="webkit">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@if ($header){{ $header }} | @endif{{ Admin::title() }}</title>
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="theme-color" content="#800000">
    <meta name="ehr-base" content="{{ rtrim(admin_url('/'), '/') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('assets/brand/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ url('assets/brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {!! Admin::css() !!}
    {{-- After laravel-admin's sheets so the house style wins; the version query refreshes browser caches. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/ehrms.css') }}?v={{ @filemtime(public_path('css/ehrms.css')) }}">

    <script src="{{ Admin::jQuery() }}"></script>
    {!! Admin::headerJs() !!}
</head>

<body class="hold-transition {{ config('admin.skin') }} {{ join(' ', config('admin.layout')) }}">

    @if ($alert = config('admin.top_alert'))
        <div style="text-align: center;padding: 5px;font-size: 12px;background-color: #ffffd5;color: #ff0000;">
            {!! $alert !!}
        </div>
    @endif

    <div class="wrapper">

        @include('admin::partials.header')

        @include('admin::partials.sidebar')

        <div class="content-wrapper" id="pjax-container">
            {!! Admin::style() !!}
            <div id="app">
                @yield('content')
            </div>
            {!! Admin::script() !!}
            {!! Admin::html() !!}
        </div>

        @include('admin::partials.footer')

    </div>

    <button id="totop" title="Go to top" style="display: none;"><i class="fa fa-chevron-up"></i></button>

    <script>
        function LA() {}
        LA.token = "{{ csrf_token() }}";
        LA.user = @json($_user_);
    </script>

    {!! Admin::js() !!}
    <script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('js/ehrms.js') }}?v={{ @filemtime(public_path('js/ehrms.js')) }}"></script>

</body>

</html>
