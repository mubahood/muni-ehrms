<div class="kpi-card fade-in">
    <div class="icon-container {{ $color_class ?? 'bg-maroon' }}">
        <i class="fa {{ $icon ?? 'fa-chart-bar' }}"></i>
    </div>
    <div class="info-container">
        <h3>{{ number_format($number ?? 0) }}</h3>
        <p>{{ $title ?? 'Metric' }}</p>
    </div>
</div>