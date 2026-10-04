<div class="ehr">
    <div class="ehr-panel">
        <div class="ehr-panel-head">
            <h3>All notifications</h3>
            @if (Admin::user()->unreadNotifications()->exists())
                <form method="post" action="{{ admin_url('notifications/read-all') }}">
                    @csrf
                    <button class="btn btn-default btn-sm">Mark all as read</button>
                </form>
            @endif
        </div>
        <div class="ehr-panel-body flush">
            @if ($notes->isEmpty())
                <div class="ehr-empty">You have no notifications yet.</div>
            @else
                <ul class="ehr-list">
                    @foreach ($notes as $note)
                        <li style="@if (!$note->read_at) box-shadow: inset 3px 0 0 var(--maroon); @endif">
                            <div class="who">
                                <b><a href="{{ admin_url('notifications/' . $note->id) }}" style="color:var(--ink)">{{ $note->data['title'] ?? 'Notification' }}</a></b>
                                <span>{{ $note->data['body'] ?? '' }}</span>
                            </div>
                            <span class="ehr-muted ehr-small" style="white-space:nowrap">{{ $note->created_at->format('d M Y, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if ($notes->hasPages())
            <div class="ehr-panel-foot">{{ $notes->links() }}</div>
        @endif
    </div>
</div>
