<?php
// Inputs / defaults
$title = $title ?? 'Title';
$sub_title = $sub_title ?? '';
$number = $number ?? '0';
$link = $link ?? 'javascript:;';
$icon = $icon ?? '<i class="fa fa-info-circle"></i>';
$is_dark = isset($is_dark) ? (bool) $is_dark : false;

// Theme classes
$cardClass = $is_dark ? 'box-5-dark' : 'box-5-light';
?>
<style>
    /* Base card */
    .box-5 {
        display: block;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
        transition: transform .15s, box-shadow .15s;
        font-family: 'Segoe UI', Roboto, Arial, sans-serif;
    }

    .box-5:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.24);
    }

    /* Layout */
    .box-5 .box-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem 1rem;
    }

    .box-5 .box-left {
        display: flex;
        align-items: center;
    }

    /* Icon */
    .box-5 .icon-container {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin-right: 1rem;
        flex-shrink: 0;
    }

    /* Text */
    .box-5 .title {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .box-5 .subtitle {
        margin: 0.3rem 0 0;
        font-size: 1.05rem;
        font-weight: 500;
        line-height: 1.3;
    }

    /* Number */
    .box-5 .number {
        font-size: 1.8rem;
        font-weight: 800;
        min-width: 3.5rem;
        text-align: right;
    }

    /* Light theme */
    .box-5-light {
        background: #fff;
        border: 1px solid #e0e0e0;
    }

    .box-5-light .icon-container {
        background: #134169;
        color: #fff;
    }

    .box-5-light .title {
        color: #222;
    }

    .box-5-light .subtitle {
        color: #555;
    }

    .box-5-light .number {
        color: #134169;
    }

    /* Dark theme */
    .box-5-dark {
        background: #134169;
        color: #fff;
        border: none;
    }

    .box-5-dark .icon-container {
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
    }

    .box-5-dark .title,
    .box-5-dark .number {
        color: #fff;
    }

    .box-5-dark .subtitle {
        color: rgba(255, 255, 255, 0.85);
    }
</style>

<a href="{{ $link }}" class="box-5 {{ $cardClass }} mb-4">
    <div class="box-content">
        <div class="box-left">
            <div class="icon-container">{!! $icon !!}</div>
            <div>
                <p class="title">{{ strtoupper($title) }}</p>
                @if ($sub_title)
                    <p class="subtitle">{{ $sub_title }}</p>
                @endif
            </div>
        </div>
        <div class="number">{{ $number }}</div>
    </div>
</a>
