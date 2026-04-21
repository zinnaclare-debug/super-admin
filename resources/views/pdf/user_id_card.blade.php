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
            top: 12pt;
            left: 0;
            right: 0;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .front-logo {
            width: 26pt;
            height: 26pt;
            margin: 0 auto 4pt;
            text-align: center;
        }
        .front-logo img,
        .watermark img,
        .qr-code img {
            max-width: 24pt;
            max-height: 24pt;
        }
        .front-logo span {
            display: inline-block;
            line-height: 24pt;
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
        }
        .school-name {
            margin: 0;
            font-size: 9.8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.32pt;
        }
        .school-motto {
            margin-top: 1pt;
            font-size: 4.4pt;
            opacity: 0.96;
        }
        .user-type {
            margin-top: 4pt;
            display: inline-block;
            padding: 2pt 7pt;
            border-radius: 999pt;
            background: rgba(255,255,255,0.16);
            border: 0.8pt solid rgba(255,255,255,0.25);
            font-size: 4.4pt;
            font-weight: 700;
            letter-spacing: 0.45pt;
            text-transform: uppercase;
        }
        .photo-ring {
            position: absolute;
            top: 84pt;
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
            bottom: 20pt;
            z-index: 2;
        }
        .front-line {
            position: absolute;
            top: 2pt;
            left: 0;
            width: 2.8pt;
            height: 56pt;
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
            top: 20pt;
            left: 18pt;
            right: 16pt;
            z-index: 2;
        }
        .terms-title {
            margin: 0 0 8pt;
            font-size: 8.7pt;
            font-weight: 700;
            color: #0f1f46;
        }
        .terms-list {
            margin: 0 0 18pt 8pt;
            padding: 0;
            font-size: 4.6pt;
            line-height: 1.55;
            color: #1f2937;
        }
        .terms-list li {
            margin-bottom: 4pt;
        }
        .middle-block {
            margin-top: 50pt;
        }
        .middle-title {
            font-size: 4.6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35pt;
            color: #102047;
        }
        .middle-name {
            margin-top: 2pt;
            font-size: 6pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#1b2554' }};
            text-transform: uppercase;
            line-height: 1.15;
        }
        .contact-list {
            margin-top: 12pt;
        }
        .contact-row {
            margin-bottom: 4.4pt;
            font-size: 4.7pt;
            line-height: 1.35;
            color: #1f2937;
            word-break: break-word;
        }
        .contact-icon {
            display: inline-block;
            width: 8pt;
            color: {{ $primaryColor ?? '#1b2554' }};
            font-weight: 700;
        }
        .watermark {
            position: absolute;
            top: 92pt;
            left: 50%;
            width: 56pt;
            height: 56pt;
            margin-left: -28pt;
            opacity: 0.1;
            z-index: 1;
            text-align: center;
        }
        .watermark img {
            max-width: 56pt;
            max-height: 56pt;
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
    $classOrPosition = $user?->role === 'student'
        ? ($displayClass ?: ($displayLevel ?: '-'))
        : ($displayPosition ?: 'STAFF MEMBER');
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
                        <rect width="153" height="243" fill="#ffffff"/>
                        <ellipse cx="76.5" cy="2" rx="64" ry="56" fill="{{ $primaryColor ?? '#1b2554' }}"/>
                        <path d="M6 0 C12 68, 10 150, 6 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3.6"/>
                        <path d="M147 0 C141 68, 143 150, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3.6"/>
                        <path d="M0 0 C13 90, 10 183, 0 243 L107 243 C89 236, 74 226, 60 210 C43 191, 36 168, 40 143 C45 117, 63 90, 91 64 C109 47, 123 26, 133 0 Z" fill="#ffffff"/>
                        <path d="M0 243 L107 243 C89 236, 74 226, 60 210 C43 191, 36 168, 40 143 C45 117, 63 90, 91 64 C109 47, 123 26, 133 0" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3"/>
                        <path d="M0 0 C10 90, 11 179, 5 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="1.9"/>
                        <path d="M148 0 C140 67, 139 150, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="1.9"/>
                        <rect x="112" y="160" width="41" height="83" fill="{{ $primaryColor ?? '#1b2554' }}"/>
                    </svg>

                    <div class="front-head">
                        <div class="front-logo">
                            @if(!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="School Logo">
                            @else
                                <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                            @endif
                        </div>
                        <h1 class="school-name">{{ $schoolName }}</h1>
                        <div class="school-motto">{{ $motto }}</div>
                        <div class="user-type">{{ $roleName }}</div>
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
                                <span class="label">{{ $user?->role === 'student' ? 'Class:' : 'Position:' }}</span>
                                <span class="value">{{ strtoupper((string) $classOrPosition) }}</span>
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
                        <path d="M0 243 C22 214, 45 200, 74 190 C96 182, 118 176, 137 169" fill="none" stroke="#ffffff" stroke-width="1.8"/>
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

                            <div class="contact-list">
                                <div class="contact-row"><span class="contact-icon">A</span>{{ $contactAddress ?: '-' }}</div>
                                <div class="contact-row"><span class="contact-icon">E</span>{{ $contactEmail ?: '-' }}</div>
                                <div class="contact-row"><span class="contact-icon">P</span>{{ $contactPhone ?: '-' }}</div>
                                <div class="contact-row"><span class="contact-icon">W</span>{{ $websiteUrl ?: '-' }}</div>
                            </div>
                        </div>
                    </div>

                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
