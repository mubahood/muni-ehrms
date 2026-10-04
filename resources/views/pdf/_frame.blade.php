{{-- Letterhead and footer, repeated on every page. Expects $config, $generatedBy, $generatedAt. --}}
<div class="band"></div>
<div class="lh">
    <table>
        <tr>
            <td class="crest"><img src="{{ public_path('assets/brand/muni-crest.png') }}" alt=""></td>
            <td>
                <div class="inst">{{ mb_strtoupper($config->company_name ?: 'Muni University') }}</div>
                <div class="office">{{ collect([$config->office_name, $config->company_address])->filter()->implode(' · ') }}</div>
                @if ($config->motto)<div class="motto">{{ $config->motto }}</div>@endif
            </td>
            <td style="width:42%">
                <div class="sys">{{ $config->system_name ?: 'Electronic Human Resource Management System' }}</div>
                <div class="contact">{{ $config->contactLine() }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>
</div>
<div class="foot">
    Generated {{ $generatedAt->format('d M Y, H:i') }} by {{ $generatedBy }}<br>
    {{ $footerNote ?? ($config->report_footer ?: 'Human Resource Office – confidential staff record') }}
</div>
