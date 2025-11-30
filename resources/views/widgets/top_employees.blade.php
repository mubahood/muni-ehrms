<div class="modern-box">
    <div class="box-header">{{ $title }}</div>
    <div class="box-content">
        <ul class="employee-list">
            @forelse($users as $user)
                <li>
                    <span class="user-info">{{ $user->name }}</span>
                    <span class="user-count {{ $count_class }}">
                        {{ $user->{$count_field} }} {{ $count_label }}
                    </span>
                </li>
            @empty
                <li>No data available.</li>
            @endforelse
        </ul>
    </div>
</div>