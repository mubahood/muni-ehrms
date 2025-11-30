@if (!isset($item))
    @php throw new Exception('VehicleRequest item not set for PDF generation'); @endphp
@endif
@php

    $hod = null;
    $gm = $item->get_gm();
    $gm_name = 'N/A';
    $gm_sig = null;

    if ($gm != null) {
        $gm_name = $gm->name;
        if ($gm->whatsapp != null) {
            if (file_exists(public_path('storage/' . $gm->whatsapp))) {
                $gm_sig = $gm->whatsapp;
            } else {
                $gm_sig = null;
            }
        }
    }

    $hod_sig = null;
    $hod_name = 'N/A';
    if ($item->department != null) {
        $hod = $item->department->hod;
        if ($hod != null) {
            $hod_name = $hod->name;
        }
    }

    if ($hod != null && $hod->whatsapp != null) {
        if (file_exists(public_path('storage/' . $hod->whatsapp))) {
            $hod_sig = $hod->whatsapp;
        } else {
            $hod_sig = null;
        }
    }

@endphp

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
                        GATE PASS / BON de SORTIE</h2>
                </td>
                <td>
                    <img style="width: 150px; height: 50px; float: right;" {{-- Use public_path for images in PDF --}}
                        src="{{ public_path('assets/images/logo.jpg') }}" alt="Company Logo">
                </td>
            </tr>
        </table>



        {{-- JOURNEY --}}
        <table class="details-table" style="margin-bottom: 20px;">

            <tr class="my-td">
                <td><b>Date</b></td>
                <td>{{ \Carbon\Carbon::parse($item->requested_departure_time)->format('d-M-Y H:i') ?? 'N/A' }}</td>
            </tr>

        </table>

        {{-- applicant --}}
        <table class="details-table" style="margin-bottom: 20px;">
            <tr class="my-td">
                <td style="width: 20%;">Name</td>
                <td>{{ $item->applicant->name ?? 'N/A' }}</td>
            </tr>
        </table>


        {{-- Material Items --}}
        @if ($item->materialItems && $item->materialItems->count() > 0)
            <table class="details-table" style="margin-bottom: 20px;">
                <thead>
                    <tr class="my-th">
                        <th>Material Requested</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Photo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->materialItems as $material)
                        <tr class="my-td">
                            <td>{{ $material->type ?? 'N/A' }}</td>
                            <td>{{ $material->quantity ?? 'N/A' }}</td>
                            <td>{{ $material->unit ?? 'N/A' }}</td>
                            <td>
                                @if (!empty($material->description))
                                    @if (file_exists(public_path('storage/' . $material->description)))
                                        <img width="200" src="{{ asset('storage/' . $material->description) }}"
                                            alt="Material Image">
                                    @else
                                        IMAGE NOT FOUND ({{ public_path('storage/' . $material->description) }})
                                    @endif
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    </div>

    <h2 style="text-align: center;">AUTHORISED SIGNATURE APPROVALS</h2>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 3px solid #000;">
        <thead>
            <tr class="my-th" style="border: 3px solid #000;">
                <th style="width: 25%; border: 3px solid #000;">Name</th>
                <th style="width: 25%; border: 3px solid #000;">Position</th>
                <th style="width: 25%; border: 3px solid #000;">Signature</th>
            </tr>
        </thead>
        <tbody>
            <tr class="my-td" style="border: 3px solid #000;">
                <td style="border: 3px solid #000;">{{ $hod_name }}</td>
                <td style="border: 3px solid #000;">Head of Department (HOD)</td>
                <td style="border: 3px solid #000; height: 80px; padding: 0px;">
                    {{-- Check if the second approver has a signature --}}
                    @if (!empty($hod_sig))
                        {{-- check if $hod_sig is not null and dsplay it as an asset --}}
                        <img width="100" src="{{ asset('storage/' . $hod_sig) }}" alt="Signature">
                    @else
                    @endif
                </td>
            </tr>
            <tr class="my-td" style="border: 3px solid #000;">
                <td style="border: 3px solid #000;">{{ $gm_name }}</td>
                <td style="border: 3px solid #000;">General Manager (GM)</td>
                <td style="border: 3px solid #000; height: 80px; padding: 0px;">
                    @if (!empty($gm_sig))
                        <img width="100" src="{{ asset('storage/' . $gm_sig) }}" alt="Signature">
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

</body>

</html>
