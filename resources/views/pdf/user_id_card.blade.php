<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper((string) ($roleLabel ?? 'ID CARD')) }}</title>
    <style>
        @page { margin: 0; size: 306pt 243pt; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
        }
        .sheet {
            width: 306pt;
            height: 243pt;
            overflow: hidden;
            background: #ffffff;
        }
        .sheet-table {
            width: 306pt;
            height: 243pt;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .sheet-table td {
            width: 153pt;
            height: 243pt;
            padding: 0;
            vertical-align: top;
        }
        .divider {
            border-left: 1pt solid #d7ddea;
        }
        .card {
            position: relative;
            width: 153pt;
            height: 243pt;
            overflow: hidden;
            background: #ffffff;
        }
        .shape {
            position: absolute;
            inset: 0;
            width: 153pt;
            height: 243pt;
            z-index: 0;
        }
        .front-head {
            position: absolute;
            top: 10pt;
            left: 12pt;
            right: 12pt;
            z-index: 2;
            color: #ffffff;
            text-align: center;
        }
        .front-brand-card {
            display: inline-block;
            min-width: 110pt;
            max-width: 125pt;
            padding: 7pt 8pt 8pt;
            border-radius: 12pt;
            background: rgba(15, 23, 42, 0.28);
            border: 0.8pt solid rgba(255,255,255,0.18);
            box-sizing: border-box;
        }
        .front-logo {
            width: 30pt;
            height: 30pt;
            margin: 0 auto 5pt;
            text-align: center;
        }
        .front-logo img,
        .watermark img {
            max-width: 28pt;
            max-height: 28pt;
        }
        .front-logo span {
            display: inline-block;
            line-height: 28pt;
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
        }
        .brand-copy {
            text-align: center;
        }
        .school-name {
            margin: 0;
            font-size: 8.8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.22pt;
            line-height: 1.2;
        }
        .school-motto {
            margin-top: 2pt;
            font-size: 4.4pt;
            opacity: 0.96;
        }
        .user-type {
            margin-top: 5pt;
            display: inline-block;
            padding: 2pt 7pt;
            border-radius: 999pt;
            background: rgba(255,255,255,0.18);
            border: 0.8pt solid rgba(255,255,255,0.3);
            font-size: 4.4pt;
            font-weight: 700;
            letter-spacing: 0.45pt;
            text-transform: uppercase;
        }
        .photo-ring {
            position: absolute;
            top: 82pt;
            left: 56pt;
            width: 72pt;
            height: 72pt;
            border-radius: 50%;
            background: {{ $primaryColor ?? '#1b2554' }};
            z-index: 2;
            box-shadow: 0 8pt 14pt rgba(15, 23, 42, 0.13);
        }
        .photo-inner {
            position: absolute;
            inset: 5pt;
            overflow: hidden;
            border-radius: 50%;
            background: #ffffff;
        }
        .photo-inner img {
            width: 62pt;
            height: 62pt;
        }
        .photo-placeholder {
            padding-top: 26pt;
            font-size: 4.4pt;
            text-align: center;
            color: #64748b;
            text-transform: uppercase;
        }
        .front-info {
            position: absolute;
            left: 15pt;
            right: 46pt;
            bottom: 28pt;
            z-index: 2;
        }
        .front-line {
            position: absolute;
            top: 2pt;
            left: 0;
            width: 2.8pt;
            height: 42pt;
            border-radius: 999pt;
            background: {{ $accentColor ?? '#ef7d00' }};
        }
        .front-copy {
            padding-left: 7pt;
        }
        .field {
            margin-bottom: 5.5pt;
        }
        .label {
            display: block;
            font-size: 4.1pt;
            color: #475569;
        }
        .value {
            display: block;
            margin-top: 1.2pt;
            font-size: 6.2pt;
            font-weight: 700;
            line-height: 1.15;
            color: #0f172a;
            word-break: break-word;
        }
        .back-panel {
            position: absolute;
            top: 14pt;
            left: 15pt;
            right: 14pt;
            z-index: 2;
        }
        .terms-title {
            margin: 0 0 7pt;
            font-size: 8.7pt;
            font-weight: 700;
            color: #0f1f46;
        }
        .terms-list {
            margin: 0 0 10pt 8pt;
            padding: 0;
            font-size: 4.5pt;
            line-height: 1.45;
            color: #1f2937;
        }
        .terms-list li {
            margin-bottom: 3.5pt;
        }
        .middle-block {
            margin-top: 22pt;
            text-align: center;
        }
        .middle-title {
            font-size: 4.6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35pt;
            color: #102047;
        }
        .middle-name {
            margin-top: 3pt;
            font-size: 6.4pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#1b2554' }};
            text-transform: uppercase;
            line-height: 1.15;
        }
        .contact-card {
            position: absolute;
            left: 12pt;
            right: 12pt;
            bottom: 12pt;
            z-index: 2;
            background: linear-gradient(135deg, {{ $primarySoft ?? '#eef2ff' }} 0%, {{ $accentSoft ?? '#fdf1e8' }} 100%);
            border: 1pt solid rgba(15, 23, 42, 0.08);
            border-radius: 10pt;
            padding: 7pt 8pt;
            box-sizing: border-box;
            box-shadow: 0 4pt 10pt rgba(15, 23, 42, 0.08);
        }
        .contact-card-title {
            margin: 0 0 6pt;
            font-size: 4.3pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.42pt;
            color: {{ $primaryColor ?? '#1b2554' }};
        }
        .contact-table {
            width: 100%;
            border-collapse: collapse;
        }
        .contact-table td {
            vertical-align: top;
            padding-bottom: 4pt;
        }
        .contact-icon-cell {
            width: 13pt;
        }
        .contact-icon-badge {
            display: inline-block;
            width: 10pt;
            height: 10pt;
            line-height: 10pt;
            border-radius: 50%;
            text-align: center;
            font-size: 5.2pt;
            font-weight: 700;
            color: #ffffff;
            background: {{ $primaryColor ?? '#1b2554' }};
        }
        .contact-text {
            font-size: 4.55pt;
            line-height: 1.28;
            color: #1f2937;
            word-break: break-word;
        }
        .watermark {
            position: absolute;
            top: 88pt;
            left: 50%;
            width: 78pt;
            height: 78pt;
            margin-left: -39pt;
            opacity: 0.12;
            z-index: 1;
            text-align: center;
        }
        .watermark img {
            max-width: 78pt;
            max-height: 78pt;
        }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL NAME'));
    $motto = trim((string) ($schoolMotto ?? ''));
    $motto = $motto !== '' ? $motto : 'school slogan text line here';
    $logoFallback = collect(explode(' ', (string) ($school?->name ?? 'SC')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $roleName = strtoupper((string) ($user?->role ?? 'USER'));
    $idNumber = $identityNumber ?: '-';
    $bullets = [
        $user?->role === 'student'
            ? 'This card identifies the student and should be presented on request.'
            : 'This card identifies the staff member and should be presented on request.',
        'Replacement is required if the card is damaged or lost.',
        'Unauthorized use of this card is not allowed.',
    ];
@endphp

<div class="sheet">
    <table class="sheet-table">
        <tr>
            <td>
                <div class="card">
                    <svg class="shape" viewBox="0 0 153 243" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="153" height="243" fill="{{ $primaryColor ?? '#1b2554' }}"/>
                        <ellipse cx="76.5" cy="4" rx="67" ry="60" fill="{{ $primaryColor ?? '#1b2554' }}"/>
                        <path d="M6 0 C12 68, 10 150, 6 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="4"/>
                        <path d="M147 0 C141 68, 143 150, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="4"/>
                        <path d="M0 0 C13 90, 10 183, 0 243 L107 243 C89 236, 74 226, 60 210 C43 191, 36 168, 40 143 C45 117, 63 90, 91 64 C109 47, 123 26, 133 0 Z" fill="#ffffff"/>
                        <path d="M0 243 L107 243 C89 236, 74 226, 60 210 C43 191, 36 168, 40 143 C45 117, 63 90, 91 64 C109 47, 123 26, 133 0" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3"/>
                        <path d="M0 0 C10 90, 11 179, 5 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="1.9"/>
                        <path d="M148 0 C140 67, 139 150, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="1.9"/>
                        <path d="M122 154 C134 176, 145 204, 153 243 L153 154 Z" fill="{{ $accentColor ?? '#ef7d00' }}"/>
                        <path d="M116 148 C131 177, 142 206, 149 243" fill="none" stroke="#ffffff" stroke-width="1.8"/>
                    </svg>

                    <div class="front-head">
                        <div class="front-brand-card">
                            <div class="front-logo">
                                @if(!empty($logoDataUri))
                                    <img src="{{ $logoDataUri }}" alt="School Logo">
                                @else
                                    <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                                @endif
                            </div>
                            <div class="brand-copy">
                                <h1 class="school-name">{{ $schoolName }}</h1>
                                <div class="school-motto">{{ $motto }}</div>
                                <div class="user-type">{{ $roleName }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="photo-ring">
                        <div class="photo-inner">
                            @if(!empty($userPhotoDataUri))
                                <img src="{{ $userPhotoDataUri }}" alt="User Photo">
                            @else
                                <div class="photo-placeholder">Photo</div>
                            @endif
                        </div>
                    </div>

                    <div class="front-info">
                        <div class="front-line"></div>
                        <div class="front-copy">
                            <div class="field">
                                <span class="label">Name:</span>
                                <span class="value">{{ strtoupper((string) ($user?->name ?? '-')) }}</span>
                            </div>
                            <div class="field">
                                <span class="label">ID Number:</span>
                                <span class="value">{{ $idNumber }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
            <td class="divider">
                <div class="card">
                    <svg class="shape" viewBox="0 0 153 243" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="153" height="243" fill="#ffffff"/>
                        <path d="M0 0 L153 0 L153 20 C116 21, 88 30, 64 44 C43 56, 22 58, 0 51 Z" fill="{{ $primaryColor ?? '#1b2554' }}"/>
                        <path d="M0 243 C16 215, 39 196, 67 183 C84 175, 105 169, 129 162 C143 158, 150 151, 153 143 L153 243 Z" fill="{{ $accentColor ?? '#ef7d00' }}"/>
                        <path d="M0 243 C22 214, 45 200, 74 190 C96 182, 118 176, 137 169" fill="none" stroke="#ffffff" stroke-width="2"/>
                        <path d="M0 0 C21 8, 43 17, 63 28 C84 40, 103 44, 126 42" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="1.5"/>
                    </svg>

                    @if(!empty($logoDataUri))
                        <div class="watermark"><img src="{{ $logoDataUri }}" alt=""></div>
                    @endif

                    <div class="back-panel">
                        <h2 class="terms-title">Terms &amp; Conditions</h2>
                        <ul class="terms-list">
                            @foreach($bullets as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>

                        <div class="middle-block">
                            <div class="middle-title">Head of School</div>
                            <div class="middle-name">{{ strtoupper((string) ($principalName ?? 'HEAD OF SCHOOL')) }}</div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-card-title">School Contact</div>
                        <table class="contact-table">
                            <tr>
                                <td class="contact-icon-cell"><span class="contact-icon-badge">&#8962;</span></td>
                                <td class="contact-text">{{ $contactAddress ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="contact-icon-cell"><span class="contact-icon-badge">&#9993;</span></td>
                                <td class="contact-text">{{ $contactEmail ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="contact-icon-cell"><span class="contact-icon-badge">&#9742;</span></td>
                                <td class="contact-text">{{ $contactPhone ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>

                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
