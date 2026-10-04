<div class="ehr">
    <div class="ehr-toolbar">
        <div class="ehr-muted">
            @if ($last)
                Last leave taken: <b style="color:var(--ink)">{{ $last->typeLabel() }}</b>, {{ $last->start_date->format('d M') }} – {{ $last->end_date->format('d M Y') }}
            @else
                No leave taken yet.
            @endif
        </div>
        <a href="{{ admin_url('my-leave/apply') }}" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp; Apply for leave</a>
    </div>

    <div class="ehr-grid">
        <div class="span-4">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Annual leave {{ $balance->label() }}</h3></div>
                <div class="ehr-panel-body">
                    @if (!$balance->hasAllocation)
                        <div class="ehr-note warn">No annual leave has been allocated to you for {{ $balance->label() }} yet. Please contact Human Resource. Other types of leave can still be applied for.</div>
                    @else
                        <div style="display:flex;align-items:baseline;gap:8px">
                            <span style="font-size:34px;font-weight:700;letter-spacing:-.02em">{{ $balance->available() }}</span>
                            <span class="ehr-muted">days you can still apply for</span>
                        </div>
                        <div class="ehr-meter">
                            <span class="taken" style="width: {{ 100 * $balance->taken / max(1, $balance->total()) }}%"></span>
                            <span class="pending" style="width: {{ 100 * $balance->pending / max(1, $balance->total()) }}%"></span>
                        </div>
                        <div class="ehr-legend" style="margin-bottom:12px">
                            <span><i style="background:var(--maroon)"></i>Taken</span>
                            <span><i style="background:#c98a8a"></i>Awaiting approval</span>
                        </div>
                        <dl class="ehr-dl" style="grid-template-columns:1fr auto">
                            <dt>(a) Days due this year</dt><dd>{{ $balance->daysDue }}</dd>
                            <dt>(b) Carried forward</dt><dd>{{ $balance->carriedForward }}</dd>
                            <dt>(c) Taken</dt><dd>{{ $balance->taken }}</dd>
                            <dt>(d) Balance</dt><dd><b>{{ $balance->balance() }}</b></dd>
                            <dt>Awaiting approval</dt><dd>{{ $balance->pending }}</dd>
                        </dl>
                    @endif
                </div>
                <div class="ehr-panel-foot">Requests awaiting approval hold their days until they are decided.</div>
            </div>
        </div>

        <div class="span-8">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>My requests</h3><span class="ehr-small">{{ $leaves->count() }} in total</span></div>
                <div class="ehr-panel-body flush ehr-table-wrap">
                    @if ($leaves->isEmpty())
                        <div class="ehr-empty">You have not applied for leave yet.</div>
                    @else
                        @include('ehrms.leave._table', ['leaves' => $leaves, 'showPerson' => false])
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
