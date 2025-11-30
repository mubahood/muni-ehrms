@if (!isset($item))
    @php throw new Exception('VehicleRequest item not set for PDF generation'); @endphp
@endif

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass Request #{{ $item->id }}</title>
    {{-- Ensure CSS is correctly linked using public_path for dompdf --}}
    <link rel="stylesheet" href="{{ public_path('css/print.css') }}">
    {{-- You might need a specific print CSS, Bootstrap print, or custom styles --}}
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.4;
            font-size: 12px;
        }

        .container {
            padding: 20px;
        }

        .header,
        .footer {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            max-width: 150px;
            margin-bottom: 10px;
        }

        .header h2,
        .header p {
            margin: 0;
        }

        .divider {
            border-top: 2px solid black;
            margin: 15px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin-top: 15px;
            margin-bottom: 5px;
            text-decoration: underline;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .details-table th,
        .details-table td {
            border: 3px solid #000000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            min-width: 150px;
        }

        .value {
            display: inline-block;
        }

        .approval-section {
            margin-top: 20px;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-block {
            display: inline-block;
            width: 30%;
            margin-right: 3%;
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid black;
            margin-top: 30px;
            margin-bottom: 5px;
        }

        .text-center {
            text-align: center;
        }

        .text-danger {
            color: #dc3545;
        }

        .text-success {
            color: #28a745;
        }

        .text-warning {
            color: #ffc107;
        }

        .my-th {
            style="background-color: #c9c3c3; font-weight: bold; text-align: center!mportant; font-size: 14px;"
        }

        .my-th td {
            background-color: #c9c3c3;
            font-weight: bold;
            text-align: center !important;
            font-size: 14px;
        }

        .my-td {
            text-align: center !important;
            font-size: 14px;
        }

        .my-td td {
            text-align: center !important;
            font-size: 14px;
        }

        /* Add other styles as needed */
    </style>
</head>

<body>
    <div class="container">
        <table
            style="width: 100%; margin-bottom: 20px; background-color: #189A47; color: white; padding: 10px; padding-bottom: 20px;">
            <tr>
                <td>
                    <h2 style="margin: 0; color: white; font-size: 20px; font-weight: bold;">
                        Resident & Vehicle Off Site Travel Approval</h2>
                </td>
                <td>
                    <img style="width: 150px; height: 50px; float: right;" {{-- Use public_path for images in PDF --}}
                        src="{{ public_path('assets/images/logo.jpg') }}" alt="Company Logo">
                </td>
            </tr>
        </table>

        <p class="text-center" style="font-weight: bold; color: red; font-size: 18px; margin-bottom: 24px;">
            Off-site travel for all Syama camp residents and SOMISY vehicles is restricted to <u>essential business
                purposes</u> only
        </p>


        {{-- applicant --}}
        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-td">
                <td style="width: 20%;">Name</td>
                <td>{{ $item->applicant->name ?? 'N/A' }}</td>
            </tr>
            <tr class="my-td">
                <td style="width: 20%;">Position</td>
                <td>{{ $item->applicant->position ?? 'N/A' }}</td>
            </tr>
        </table>


        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-th">
                <td>SOMISY VEHICLE</td>
                <td>CAMP RESIDENT</td>
                <td>EXPATRIATE</td>
            </tr>
            <tr class="my-td">
                <td>{{ $item->is_somisy_vehicle ? 'Yes' : 'No' }}</td>
                <td>{{ $item->is_camp_resident ? 'Yes' : 'No' }}</td>
                <td>{{ $item->expatirate_type ?? 'N/A' }}</td>
            </tr>
        </table>
        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-th">
                <td colspan="4"><b>VEHICLE AND DRIVER</b></td>
            </tr>
            <tr class="my-td">
                <td><b>Driver name</b></td>
                <td>{!! $item->drivers->pluck('driver.name')->implode('<br>') ?? 'N/A' !!}</td>
                <td>
                    Phone
                </td>
                <td>{!! $item->drivers->pluck('driver.phone_number')->implode('<br>') ?? 'N/A' !!}</td>
            </tr>
            <tr>
                <td><b>Licence(s)</b></td>
                <td colspan="3">{{ $item->licence_type }}</td>
            </tr>
            <tr>
                <td><b>Vehicle</b></td>
                <td>{{ $item->vehicle->registration_number ?? 'N/A' }}</td>
                <td><b>Make/Model</b></td>
                <td>{{ $item->vehicle->brand ?? '' }} {{ $item->vehicle->model ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><b>Department</b></td>
                <td>{{ $item->applicant && $item->applicant->department ? $item->applicant->department->name : 'N/A' }}
                </td>
                <td><b>Company</b></td>
                <td>{{ $item->applicant && $item->applicant->company ? $item->applicant->company->name : 'N/A' }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-center">
                    <br>
                    <div class="section-title">Conditions</div>
                    <ul style="font-size: 16px; margin-left: 20px; color: #333;">
                        <li style="color: red;">Recurring trips will be approved for a maximum of one business week in
                            advance
                            (Tuesday - Wednesday).</li>
                        <li>If more than one resident, HOD who owns the vehicle signs off.</li>
                        <li>If no site vehicle is used, each resident completes their own form.</li>
                        <li>Licence must be carried by driver and all local road rules obeyed.</li>
                        <li>The driver is responsible for the security of the vehicle at all times.</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Driver signature</b>
                </td>
                <td>
                    <br>
                    <br>
                </td>
                <td><b>Date</b></td>
                <td>
                    {{ \Carbon\Carbon::now()->format('d-M-Y') }}
                </td>
            </tr>
        </table>

        {{-- JOURNEY --}}
        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-th">
                <td colspan="4"><b>JOURNEY</b></td>
            </tr>

            <tr class="my-td">
                <td><b>Date Valid From</b></td>
                <td>{{ \Carbon\Carbon::parse($item->requested_departure_time)->format('d-M-Y H:i') ?? 'N/A' }}</td>
                <td><b>Date Until</b></td>
                <td>{{ \Carbon\Carbon::parse($item->requested_return_time)->format('d-M-Y H:i') ?? 'N/A' }}</td>
            </tr>
            <tr class="my-td">
                <td colspan="2"><b>Date Valid From</b></td>
                <td colspan="2">{{ $item->destination ?? 'N/A' }}</td>
            </tr>
            <tr class="my-td">
                <td colspan="2"><b>Justification</b></td>
                <td colspan="2">{{ $item->justification ?? 'N/A' }}</td>
            </tr>
        </table>

        {{-- Passenger => materials_requested --}}
        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-th">
                <td colspan="4"><b>PASSENGERS</b></td>
            </tr>
            <tr class="my-td">
                <td colspan="4">{{ $item->materials_requested ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>
</body>

</html>
