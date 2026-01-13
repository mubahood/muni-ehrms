<div class="modern-box fade-in">
    <div class="box-header">
        <i class="fa fa-users" style="margin-right: 8px; color: var(--muni-maroon, #800000);"></i>
        {{ $title }}
    </div>
    <div class="box-content" style="padding: 0;">
        @if($users->isEmpty())
            <div style="padding: 2rem; text-align: center; color: #718096;">
                <i class="fa fa-info-circle" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                No data available
            </div>
        @else
            <ul class="employee-list">
                @foreach($users as $index => $user)
                <li>
                    <span style="color: #718096; font-size: 12px; width: 24px;">{{ $index + 1 }}.</span>
                    <span class="user-name" style="flex: 1; margin-left: 8px;">{{ $user->name }}</span>
                    <span class="user-count {{ $count_class ?? 'count-danger' }}">{{ $user->{$count_field} }} {{ $count_label }}</span>
                </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>