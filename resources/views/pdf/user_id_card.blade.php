<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper((string) ($roleLabel ?? 'ID CARD')) }}</title>
    <style>
        @page { margin: 14px; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
        }
        .board {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
        }
        .board > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }
        .card {
            position: relative;
            height: 500px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #d7deea;
        }
        .shape-svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .front-top {
            position: absolute;
            top: 18px;
            left: 0;
            right: 0;
            z-index: 2;
            text-align: center;
            color: #ffffff;
        }
        .front-logo {
            width: 72px;
            height: 72px;
            margin: 0 auto 12px;
            background: #ffffff;
            border-radius: 50%;
            display: table;
            text-align: center;
        }
        .front-logo span {
            display: table-cell;
            vertical-align: middle;
            font-weight: 700;
            font-size: 20px;
            color: {{ $primaryColor ?? '#1a2756' }};
            text-transform: uppercase;
        }
        .front-logo img,
        .back-logo img {
            max-width: 44px;
            max-height: 44px;
            margin-top: 14px;
        }
        .front-school-name {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .front-school-motto {
            margin-top: 4px;
            font-size: 11px;
            opacity: 0.95;
        }
        .photo-ring {
            position: absolute;
            top: 118px;
            left: 144px;
            width: 168px;
            height: 168px;
            border-radius: 50%;
            background: {{ $primaryColor ?? '#1a2756' }};
            z-index: 2;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.18);
        }
        .photo-ring-inner {
            position: absolute;
            inset: 11px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
        }
        .photo-ring-inner img {
            width: 146px;
            height: 146px;
        }
        .front-info {
            position: absolute;
            left: 36px;
            right: 176px;
            bottom: 58px;
            z-index: 2;
        }
        .id-strip {
            position: absolute;
            top: 4px;
            left: 0;
            width: 8px;
            height: 128px;
            border-radius: 999px;
            background: {{ $accentColor ?? '#0f766e' }};
        }
        .front-info-inner {
            padding-left: 18px;
        }
        .field {
            margin-bottom: 12px;
        }
        .field-label {
            display: block;
            font-size: 10px;
            color: #334155;
        }
        .field-value {
            display: block;
            margin-top: 4px;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.2;
        }
        .field-value--small {
            font-size: 14px;
        }
        .front-role {
            position: absolute;
            top: 298px;
            left: 36px;
            z-index: 2;
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            background: {{ $primaryColor ?? '#1a2756' }};
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .back-inner {
            position: relative;
            z-index: 2;
            padding: 122px 38px 36px 44px;
        }
        .back-heading {
            margin: 0 0 18px;
            font-size: 22px;
            font-weight: 500;
            color: #111827;
        }
        .back-list {
            margin: 0 0 28px 22px;
            padding: 0;
            font-size: 12px;
            line-height: 1.55;
            color: #1f2937;
        }
        .back-list li {
            margin-bottom: 8px;
        }
        .signature-block {
            margin-top: 6px;
            display: table;
            width: 100%;
        }
        .signature-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 18px;
        }
        .signature-line {
            margin-top: 26px;
            border-top: 1px solid #0f172a;
            height: 12px;
        }
        .signature-title {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 700;
        }
        .signature-sub {
            font-size: 11px;
            color: #475569;
        }
        .contact-list {
            margin-top: 28px;
        }
        .contact-row {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            font-size: 12px;
            color: #1f2937;
        }
        .contact-icon,
        .contact-text {
            display: table-cell;
            vertical-align: top;
        }
        .contact-icon {
            width: 20px;
            font-size: 14px;
            color: {{ $primaryColor ?? '#1a2756' }};
        }
        .back-logo {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 18px;
            text-align: center;
            z-index: 2;
            color: #ffffff;
        }
        .back-logo-badge {
            width: 84px;
            height: 84px;
            margin: 0 auto 10px;
            background: #ffffff;
            border-radius: 50%;
            display: table;
            text-align: center;
        }
        .back-logo-badge span {
            display: table-cell;
            vertical-align: middle;
            font-weight: 700;
            font-size: 22px;
            color: {{ $primaryColor ?? '#1a2756' }};
            text-transform: uppercase;
        }
        .back-logo-name {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .back-logo-motto {
            margin-top: 4px;
            font-size: 11px;
        }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL'));
    $motto = trim((string) ($schoolMotto ?? ''));
    $motto = $motto !== '' ? $motto : strtoupper((string) ($roleLabel ?? 'ID CARD'));
    $logoFallback = collect(explode(' ', (string) ($school?->name ?? 'SC')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $frontSecondaryLabel = $user?->role === 'student' ? 'Class' : 'Position';
    $frontSecondaryValue = $user?->role === 'student'
        ? ($displayClass ?: ($displayLevel ?: '-'))
        : ($displayPosition ?: 'Staff Member');
    $frontTertiaryLabel = $user?->role === 'student' ? 'ID Number' : 'Staff Number';
    $frontTertiaryValue = $identityNumber ?? '-';
    $backBullets = [
        $user?->role === 'student'
            ? 'This card identifies the student and should be presented on request.'
            : 'This card identifies the staff member and should be presented on request.',
        $user?->role === 'student'
            ? 'Replacement is required if the card is damaged or lost.'
            : 'This card remains school property and must be returned when requested.',
        'Unauthorized use of this card is not allowed.',
    ];
@endphp

<table class="board">
    <tr>
        <td>
            <div class="card">
                <svg class="shape-svg" viewBox="0 0 330 500" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                    <rect width="330" height="500" fill="{{ $primaryColor ?? '#1a2756' }}" />
                    <ellipse cx="165" cy="-25" rx="158" ry="156" fill="{{ $primaryColor ?? '#1a2756' }}" />
                    <path d="M-10 32 C30 118, 28 262, -10 502 L275 502 C218 446, 172 404, 138 344 C108 292, 142 232, 214 170 C286 108, 319 68, 334 0 L334 502 L-10 502 Z" fill="#ffffff"/>
                    <path d="M11 41 C46 128, 42 251, 12 470 L262 470 C216 426, 178 392, 150 342 C124 296, 154 243, 220 185 C282 130, 311 92, 322 22" fill="none" stroke="{{ $accentColor ?? '#0f766e' }}" stroke-width="8" />
                </svg>

                <div class="front-top">
                    <div class="front-logo">
                        @if(!empty($logoDataUri))
                            <img src="{{ $logoDataUri }}" alt="School Logo">
                        @else
                            <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                        @endif
                    </div>
                    <h1 class="front-school-name">{{ $schoolName }}</h1>
                    <div class="front-school-motto">{{ $motto }}</div>
                </div>

                <div class="photo-ring">
                    <div class="photo-ring-inner">
                        @if(!empty($userPhotoDataUri))
                            <img src="{{ $userPhotoDataUri }}" alt="Profile Photo">
                        @endif
                    </div>
                </div>

                <div class="front-role">{{ strtoupper((string) ($user?->role ?? 'user')) }}</div>

                <div class="front-info">
                    <div class="id-strip"></div>
                    <div class="front-info-inner">
                        <div class="field">
                            <span class="field-label">Name:</span>
                            <span class="field-value">{{ $user?->name ?? '-' }}</span>
                        </div>
                        <div class="field">
                            <span class="field-label">{{ $frontSecondaryLabel }}:</span>
                            <span class="field-value field-value--small">{{ $frontSecondaryValue }}</span>
                        </div>
                        <div class="field">
                            <span class="field-label">{{ $frontTertiaryLabel }}:</span>
                            <span class="field-value field-value--small">{{ $frontTertiaryValue }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </td>
        <td>
            <div class="card">
                <svg class="shape-svg" viewBox="0 0 330 500" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" aria-hidden="true">
                    <rect width="330" height="500" fill="#ffffff" />
                    <path d="M0 0 L330 0 L330 54 C252 58, 176 94, 116 150 C78 185, 36 186, 0 180 Z" fill="{{ $primaryColor ?? '#1a2756' }}" />
                    <circle cx="288" cy="58" r="34" fill="{{ $primarySoft ?? '#dbeafe' }}" opacity="0.55" />
                    <path d="M52 500 C72 438, 124 406, 198 396 C258 388, 303 350, 330 296 L330 500 Z" fill="{{ $primaryColor ?? '#1a2756' }}" />
                    <path d="M0 500 L0 430 C52 404, 116 392, 186 396 C244 400, 292 382, 330 346 L330 500 Z" fill="#ffffff" opacity="0.98" />
                </svg>

                <div class="back-inner">
                    <h2 class="back-heading">Terms &amp; Conditions</h2>
                    <ul class="back-list">
                        @foreach($backBullets as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>

                    <div class="signature-block">
                        <div class="signature-col">
                            <div class="signature-line"></div>
                            <div class="signature-title">Signature Authority</div>
                        </div>
                        <div class="signature-col">
                            <div class="signature-line"></div>
                            <div class="signature-title">Principal</div>
                            <div class="signature-sub">{{ $principalName ?? 'Principal' }}</div>
                        </div>
                    </div>

                    <div class="contact-list">
                        <div class="contact-row">
                            <div class="contact-icon">⌂</div>
                            <div class="contact-text">{{ $contactAddress ?: '-' }}</div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-icon">✉</div>
                            <div class="contact-text">{{ $contactEmail ?: '-' }}</div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-icon">☎</div>
                            <div class="contact-text">{{ $contactPhone ?: '-' }}</div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-icon">◎</div>
                            <div class="contact-text">{{ $websiteUrl ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="back-logo">
                    <div class="back-logo-badge">
                        @if(!empty($logoDataUri))
                            <img src="{{ $logoDataUri }}" alt="School Logo">
                        @else
                            <span>{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                        @endif
                    </div>
                    <div class="back-logo-name">{{ $schoolName }}</div>
                    <div class="back-logo-motto">{{ $motto }}</div>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
