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
            border-left: 1pt solid #d9dee9;
        }
        .card {
            position: relative;
            width: 153pt;
            height: 243pt;
            overflow: hidden;
            background: #ffffff;
        }
        .front-shape,
        .back-shape {
            position: absolute;
            inset: 0;
            width: 153pt;
            height: 243pt;
            z-index: 0;
        }
        .logo-center {
            position: absolute;
            top: 12pt;
            left: 0;
            right: 0;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .logo-badge {
            width: 26pt;
            height: 26pt;
            margin: 0 auto 4pt;
            text-align: center;
        }
        .logo-badge img,
        .back-bottom-logo img,
        .watermark img {
            max-width: 24pt;
            max-height: 24pt;
        }
        .logo-badge span,
        .back-bottom-logo span {
            display: inline-block;
            font-size: 10pt;
            font-weight: 700;
            line-height: 24pt;
            text-transform: uppercase;
        }
        .school-name {
            margin: 0;
            font-size: 9.2pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        .school-motto {
            margin-top: 1pt;
            font-size: 4.5pt;
            opacity: 0.95;
        }
        .photo-ring {
            position: absolute;
            top: 78pt;
            left: 54pt;
            width: 76pt;
            height: 76pt;
            border-radius: 50%;
            background: {{ $primaryColor ?? '#1c2554' }};
            z-index: 2;
            box-shadow: 0 8pt 18pt rgba(10, 20, 44, 0.12);
        }
        .photo-inner {
            position: absolute;
            inset: 5pt;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
        }
        .photo-inner img {
            width: 66pt;
            height: 66pt;
        }
        .photo-placeholder {
            padding-top: 27pt;
            text-align: center;
            font-size: 4.6pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .role-chip {
            position: absolute;
            top: 161pt;
            left: 16pt;
            z-index: 2;
            padding: 2.2pt 8pt;
            border-radius: 999pt;
            background: {{ $primaryColor ?? '#1c2554' }};
            color: #ffffff;
            font-size: 4.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .front-info {
            position: absolute;
            left: 14pt;
            right: 48pt;
            bottom: 22pt;
            z-index: 2;
        }
        .front-accent {
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
            margin-bottom: 6pt;
        }
        .label {
            display: block;
            font-size: 4.3pt;
            color: #334155;
        }
        .value {
            display: block;
            margin-top: 1.4pt;
            font-size: 6.4pt;
            font-weight: 700;
            line-height: 1.15;
            word-break: break-word;
        }
        .back-panel {
            position: absolute;
            top: 48pt;
            left: 18pt;
            right: 16pt;
            z-index: 2;
        }
        .terms-title {
            margin: 0 0 10pt;
            font-size: 8.8pt;
            font-weight: 700;
            color: #102047;
        }
        .terms-list {
            margin: 0 0 14pt 8pt;
            padding: 0;
            font-size: 4.6pt;
            line-height: 1.55;
            color: #1f2937;
        }
        .terms-list li {
            margin-bottom: 4pt;
        }
        .head-title {
            font-size: 4.7pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.35pt;
        }
        .head-name {
            margin-top: 2pt;
            font-size: 6pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#1c2554' }};
            text-transform: uppercase;
            line-height: 1.2;
        }
        .contact-list {
            margin-top: 16pt;
        }
        .contact-row {
            margin-bottom: 5pt;
            font-size: 4.8pt;
            line-height: 1.35;
            color: #0f172a;
        }
        .contact-icon {
            display: inline-block;
            width: 8pt;
            color: {{ $primaryColor ?? '#1c2554' }};
            font-weight: 700;
        }
        .watermark {
            position: absolute;
            top: 14pt;
            right: 16pt;
            width: 26pt;
            height: 26pt;
            opacity: 0.12;
            z-index: 1;
        }
        .back-bottom {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 10pt;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .back-bottom-logo {
            width: 24pt;
            height: 24pt;
            margin: 0 auto 3pt;
            text-align: center;
        }
        .back-bottom-name {
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        .back-bottom-motto {
            margin-top: 1pt;
            font-size: 4.2pt;
            opacity: 0.95;
        }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL LOGO'));
    $motto = trim((string) ($schoolMotto ?? ''));
    $motto = $motto !== '' ? $motto : 'school slogan text line here';
    $logoFallback = collect(explode(' ', (string) ($school?->name ?? 'SC')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $roleName = strtoupper((string) ($user?->role ?? 'user'));
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
                    <svg class="front-shape" viewBox="0 0 153 243" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="153" height="243" fill="#ffffff"/>
                        <ellipse cx="76.5" cy="-2" rx="62" ry="52" fill="{{ $primaryColor ?? '#1c2554' }}"/>
                        <path d="M6 0 C13 52, 11 131, 6 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3.4"/>
                        <path d="M147 0 C140 52, 142 131, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3.4"/>
                        <path d="M0 0 C13 86, 10 182, 0 243 L106 243 C88 236, 72 226, 58 211 C41 191, 34 167, 39 141 C44 115, 61 90, 88 65 C107 48, 123 26, 133 0 Z" fill="#ffffff"/>
                        <path d="M0 0 C12 89, 12 181, 4 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="2.2"/>
                        <path d="M147 0 C140 50, 139 125, 147 243" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="2.2"/>
                        <path d="M0 243 L106 243 C88 236, 72 226, 58 211 C41 191, 34 167, 39 141 C44 115, 61 90, 88 65 C107 48, 122 27, 132 0" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="2.8"/>
                        <rect x="111" y="160" width="42" height="83" fill="{{ $primaryColor ?? '#1c2554' }}"/>
                    </svg>

                    <div class="logo-center">
                        <div class="logo-badge">
                            @if(!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="School Logo">
                            @else
                                <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                            @endif
                        </div>
                        <h1 class="school-name">{{ $schoolName }}</h1>
                        <div class="school-motto">{{ $motto }}</div>
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

                    <div class="role-chip">{{ $roleName }}</div>

                    <div class="front-info">
                        <div class="front-accent"></div>
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
                    <svg class="back-shape" viewBox="0 0 153 243" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="153" height="243" fill="#ffffff"/>
                        <path d="M0 0 L153 0 L153 18 C119 20, 93 27, 71 40 C48 54, 24 56, 0 49 Z" fill="{{ $primaryColor ?? '#1c2554' }}"/>
                        <path d="M0 243 C13 214, 32 194, 58 181 C78 171, 101 164, 127 158 C141 154, 149 148, 153 139 L153 243 Z" fill="{{ $accentColor ?? '#ef7d00' }}"/>
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

                        <div class="head-title">Head of School</div>
                        <div class="head-name">{{ strtoupper((string) ($principalName ?? 'HEAD OF SCHOOL')) }}</div>

                        <div class="contact-list">
                            <div class="contact-row"><span class="contact-icon">⌂</span>{{ $contactAddress ?: '-' }}</div>
                            <div class="contact-row"><span class="contact-icon">✉</span>{{ $contactEmail ?: '-' }}</div>
                            <div class="contact-row"><span class="contact-icon">☎</span>{{ $contactPhone ?: '-' }}</div>
                            <div class="contact-row"><span class="contact-icon">◎</span>{{ $websiteUrl ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="back-bottom">
                        <div class="back-bottom-logo">
                            @if(!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="School Logo">
                            @else
                                <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                            @endif
                        </div>
                        <div class="back-bottom-name">{{ $schoolName }}</div>
                        <div class="back-bottom-motto">{{ $motto }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
