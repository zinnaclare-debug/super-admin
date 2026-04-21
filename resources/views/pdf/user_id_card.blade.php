<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper((string) ($roleLabel ?? 'ID CARD')) }}</title>
    <style>
        @page {
            margin: 0;
            size: 486pt 153pt;
        }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
        }
        .spread {
            width: 486pt;
            height: 153pt;
            overflow: hidden;
        }
        .spread-table {
            width: 486pt;
            height: 153pt;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .spread-table td {
            width: 243pt;
            height: 153pt;
            padding: 0;
            vertical-align: top;
        }
        .card {
            position: relative;
            width: 243pt;
            height: 153pt;
            overflow: hidden;
            background: #ffffff;
        }
        .front-svg,
        .back-svg {
            position: absolute;
            inset: 0;
            width: 243pt;
            height: 153pt;
            z-index: 0;
        }
        .front-top {
            position: absolute;
            top: 8pt;
            left: 0;
            right: 0;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .front-logo {
            width: 26pt;
            height: 26pt;
            margin: 0 auto 3pt;
            text-align: center;
        }
        .front-logo img,
        .back-bottom-logo img {
            max-width: 24pt;
            max-height: 24pt;
        }
        .front-logo span,
        .back-bottom-logo span {
            display: inline-block;
            font-size: 10pt;
            font-weight: 700;
            color: #ffffff;
            line-height: 24pt;
            text-transform: uppercase;
        }
        .school-name {
            margin: 0;
            font-size: 10.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        .school-motto {
            margin-top: 1pt;
            font-size: 4.8pt;
            line-height: 1.25;
            opacity: 0.95;
        }
        .photo-wrap {
            position: absolute;
            top: 44pt;
            left: 98pt;
            width: 78pt;
            height: 78pt;
            border-radius: 50%;
            background: {{ $primaryColor ?? '#1d2758' }};
            z-index: 2;
        }
        .photo-inner {
            position: absolute;
            inset: 5pt;
            overflow: hidden;
            border-radius: 50%;
            background: #ffffff;
        }
        .photo-inner img {
            width: 68pt;
            height: 68pt;
        }
        .photo-placeholder {
            padding-top: 27pt;
            text-align: center;
            font-size: 5pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .front-role {
            position: absolute;
            top: 117pt;
            left: 20pt;
            z-index: 2;
            padding: 2pt 7pt;
            border-radius: 999pt;
            background: {{ $primaryColor ?? '#1d2758' }};
            color: #ffffff;
            font-size: 4.6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .front-info {
            position: absolute;
            top: 121pt;
            left: 21pt;
            z-index: 2;
            width: 108pt;
        }
        .front-accent-line {
            position: absolute;
            top: 4pt;
            left: 0;
            width: 3pt;
            height: 44pt;
            border-radius: 999pt;
            background: {{ $accentColor ?? '#ef7d00' }};
        }
        .front-info-inner {
            padding-left: 8pt;
        }
        .front-field {
            margin-bottom: 4.5pt;
        }
        .front-label {
            display: block;
            font-size: 4.3pt;
            color: #334155;
        }
        .front-value {
            display: block;
            margin-top: 1pt;
            font-size: 6pt;
            font-weight: 700;
            line-height: 1.15;
            color: #0f172a;
        }
        .back-panel {
            position: absolute;
            z-index: 2;
            top: 26pt;
            left: 32pt;
            right: 20pt;
        }
        .terms-title {
            margin: 0 0 9pt;
            font-size: 8.8pt;
            font-weight: 700;
            color: #0f172a;
        }
        .terms-list {
            margin: 0 0 10pt 9pt;
            padding: 0;
            font-size: 4.6pt;
            line-height: 1.5;
            color: #0f172a;
        }
        .terms-list li {
            margin-bottom: 3pt;
        }
        .head-block {
            margin-top: 10pt;
            font-size: 4.6pt;
            color: #475569;
        }
        .head-label {
            font-size: 4.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            color: #0f172a;
        }
        .head-name {
            margin-top: 2pt;
            font-size: 6pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#1d2758' }};
            text-transform: uppercase;
        }
        .contact-list {
            margin-top: 12pt;
        }
        .contact-row {
            margin-bottom: 4pt;
            font-size: 4.8pt;
            color: #1f2937;
            line-height: 1.35;
        }
        .contact-icon {
            display: inline-block;
            width: 8pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#1d2758' }};
        }
        .back-bottom {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 8pt;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .back-bottom-logo {
            width: 28pt;
            height: 28pt;
            margin: 0 auto 2pt;
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
            font-size: 4.5pt;
            line-height: 1.2;
        }
        .watermark {
            position: absolute;
            top: 9pt;
            right: 13pt;
            width: 26pt;
            height: 26pt;
            opacity: 0.14;
            z-index: 1;
        }
        .watermark img {
            max-width: 26pt;
            max-height: 26pt;
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
    $classOrPosition = $user?->role === 'student'
        ? ($displayClass ?: ($displayLevel ?: '-'))
        : ($displayPosition ?: 'Staff Member');
    $idNumber = $identityNumber ?: '-';
    $bullets = [
        $user?->role === 'student'
            ? 'This card identifies the student and should be presented on request.'
            : 'This card identifies the staff member and should be presented on request.',
        'Replacement is required if the card is damaged or lost.',
        'Unauthorized use of this card is not allowed.',
    ];
@endphp

<div class="spread">
    <table class="spread-table">
        <tr>
            <td>
                <div class="card">
                    <svg class="front-svg" viewBox="0 0 243 153" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="243" height="153" fill="#ffffff"/>
                        <ellipse cx="121.5" cy="-18" rx="98" ry="58" fill="{{ $primaryColor ?? '#1d2758' }}"/>
                        <path d="M9 0 C18 48, 16 93, 10 153" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="4"/>
                        <path d="M234 0 C225 48, 227 93, 233 153" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="4"/>
                        <path d="M0 0 C18 64, 12 118, 0 153 L168 153 C137 140, 112 121, 92 96 C74 74, 80 51, 110 26 C133 7, 148 0, 243 0 L243 153 L0 153 Z" fill="#ffffff"/>
                        <path d="M0 0 C19 66, 16 118, 8 153" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="2.8"/>
                        <path d="M235 0 C225 44, 223 93, 233 153" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="2.8"/>
                        <path d="M0 153 L167 153 C137 141, 112 122, 92 98 C74 76, 80 52, 110 28 C133 8, 147 0, 243 0" fill="none" stroke="{{ $accentColor ?? '#ef7d00' }}" stroke-width="3.4"/>
                        <rect x="179" y="92" width="64" height="61" fill="{{ $primaryColor ?? '#1d2758' }}"/>
                    </svg>

                    <div class="front-top">
                        <div class="front-logo">
                            @if(!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="School Logo">
                            @else
                                <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                            @endif
                        </div>
                        <h1 class="school-name">{{ $schoolName }}</h1>
                        <div class="school-motto">{{ $motto }}</div>
                    </div>

                    <div class="photo-wrap">
                        <div class="photo-inner">
                            @if(!empty($userPhotoDataUri))
                                <img src="{{ $userPhotoDataUri }}" alt="User Photo">
                            @else
                                <div class="photo-placeholder">Photo</div>
                            @endif
                        </div>
                    </div>

                    <div class="front-role">{{ strtoupper((string) ($user?->role ?? 'user')) }}</div>

                    <div class="front-info">
                        <div class="front-accent-line"></div>
                        <div class="front-info-inner">
                            <div class="front-field">
                                <span class="front-label">Name:</span>
                                <span class="front-value">{{ strtoupper((string) ($user?->name ?? '-')) }}</span>
                            </div>
                            <div class="front-field">
                                <span class="front-label">{{ $user?->role === 'student' ? 'Class:' : 'Position:' }}</span>
                                <span class="front-value">{{ strtoupper((string) $classOrPosition) }}</span>
                            </div>
                            <div class="front-field">
                                <span class="front-label">ID Number:</span>
                                <span class="front-value">{{ $idNumber }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="card">
                    <svg class="back-svg" viewBox="0 0 243 153" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                        <rect width="243" height="153" fill="#ffffff"/>
                        <path d="M0 0 L243 0 L243 16 C187 18, 139 31, 100 55 C66 77, 32 77, 0 68 Z" fill="{{ $primaryColor ?? '#1d2758' }}"/>
                        <path d="M0 153 C23 128, 62 115, 117 111 C165 108, 205 93, 243 64 L243 153 Z" fill="{{ $accentColor ?? '#ef7d00' }}"/>
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

                        <div class="head-block">
                            <div class="head-label">Head of School</div>
                            <div class="head-name">{{ strtoupper((string) ($principalName ?? 'HEAD OF SCHOOL')) }}</div>
                        </div>

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
