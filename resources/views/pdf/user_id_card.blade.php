<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper((string) ($roleLabel ?? 'ID CARD')) }}</title>
    <style>
        @page { margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 8px;
        }
        .page {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #ffffff;
        }
        .page-break {
            page-break-after: always;
        }
        .corner-primary {
            position: absolute;
            top: 0;
            left: 0;
            width: 88px;
            height: 50px;
            background: {{ $primaryColor ?? '#0f172a' }};
            border-bottom-right-radius: 28px;
        }
        .corner-accent {
            position: absolute;
            top: 14px;
            left: 14px;
            width: 48px;
            height: 12px;
            background: {{ $accentColor ?? '#0f766e' }};
            border-radius: 999px;
        }
        .front-logo-badge {
            position: absolute;
            top: 12px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid {{ $primarySoft ?? '#e2e8f0' }};
            text-align: center;
            line-height: 34px;
            box-sizing: border-box;
        }
        .front-logo-badge img,
        .back-logo img {
            max-width: 28px;
            max-height: 28px;
            vertical-align: middle;
        }
        .front-body {
            padding: 18px 14px 12px;
        }
        .title {
            margin: 0 46px 4px 60px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: {{ $primaryColor ?? '#0f172a' }};
            letter-spacing: 0.6px;
        }
        .school-name {
            margin: 0 46px 8px 60px;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .front-layout {
            width: 100%;
            border-collapse: collapse;
        }
        .front-layout td {
            vertical-align: top;
        }
        .photo-frame {
            width: 58px;
            height: 70px;
            border: 2px solid {{ $primaryColor ?? '#0f172a' }};
            border-radius: 12px;
            background: #ffffff;
            overflow: hidden;
            text-align: center;
        }
        .photo-frame img {
            width: 58px;
            height: 70px;
        }
        .photo-hint {
            padding-top: 24px;
            color: #475569;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-wrap {
            padding-left: 10px;
        }
        .name {
            font-size: 10px;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .role-pill {
            display: inline-block;
            background: {{ $accentSoft ?? '#ecfeff' }};
            color: {{ $accentColor ?? '#0f766e' }};
            border: 1px solid {{ $accentColor ?? '#0f766e' }};
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }
        .field {
            margin-bottom: 5px;
        }
        .field-label {
            display: block;
            font-size: 6px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .field-value {
            display: block;
            margin-top: 1px;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.2;
        }
        .bottom-band {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            border-top: 2px solid {{ $accentColor ?? '#0f766e' }};
            padding: 7px 14px 8px;
        }
        .bottom-band table {
            width: 100%;
            border-collapse: collapse;
        }
        .bottom-band td {
            width: 50%;
            vertical-align: top;
        }
        .back-top {
            padding: 14px 14px 0;
        }
        .back-card {
            margin: 8px 14px 0;
            background: #ffffff;
            border: 1px solid {{ $primarySoft ?? '#e2e8f0' }};
            border-radius: 12px;
            padding: 10px 10px 36px;
        }
        .back-heading {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: {{ $primaryColor ?? '#0f172a' }};
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        .contact-row {
            margin-bottom: 5px;
        }
        .contact-row strong {
            display: block;
            font-size: 6px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .contact-row span {
            display: block;
            margin-top: 1px;
            font-size: 7px;
            line-height: 1.3;
            word-break: break-word;
        }
        .signatures {
            margin-top: 9px;
            width: 100%;
            border-collapse: collapse;
        }
        .signatures td {
            width: 50%;
            vertical-align: top;
            padding-right: 6px;
        }
        .sig-line {
            border-top: 1px solid {{ $primaryColor ?? '#0f172a' }};
            height: 16px;
            margin-top: 10px;
        }
        .sig-title {
            font-size: 6px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .sig-name {
            margin-top: 1px;
            font-size: 7px;
            font-weight: 700;
        }
        .back-logo {
            position: absolute;
            left: 50%;
            bottom: 10px;
            width: 42px;
            height: 42px;
            margin-left: -21px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid {{ $accentSoft ?? '#ecfeff' }};
            text-align: center;
            line-height: 38px;
            box-sizing: border-box;
        }
        .logo-fallback {
            font-size: 9px;
            font-weight: 700;
            color: {{ $primaryColor ?? '#0f172a' }};
            text-transform: uppercase;
        }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'School'));
    $logoFallback = collect(explode(' ', (string) ($school?->name ?? 'S')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $primaryInfoLabel = $user?->role === 'student' ? 'Student ID' : 'Staff ID';
    $secondaryInfoLabel = $user?->role === 'student' ? 'Class' : 'Position';
    $secondaryInfoValue = $user?->role === 'student'
        ? ($displayClass ?: '-')
        : ($displayPosition ?: '-');
    $tertiaryInfoLabel = $user?->role === 'student' ? 'Department' : 'Assignment';
    $tertiaryInfoValue = $user?->role === 'student'
        ? ($displayDepartment ?: ($displayLevel ?: '-'))
        : ($displayClass ?: ($displayDepartment ?: ($displayLevel ?: '-')));
@endphp

<div class="page page-break">
    <div class="corner-primary"></div>
    <div class="corner-accent"></div>

    <div class="front-logo-badge">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="School Logo">
        @else
            <span class="logo-fallback">{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
        @endif
    </div>

    <div class="front-body">
        <div class="title">{{ strtoupper((string) ($roleLabel ?? 'ID Card')) }}</div>
        <div class="school-name">{{ $schoolName }}</div>

        <table class="front-layout">
            <tr>
                <td style="width:60px;">
                    <div class="photo-frame">
                        @if(!empty($userPhotoDataUri))
                            <img src="{{ $userPhotoDataUri }}" alt="Profile Photo">
                        @else
                            <div class="photo-hint">Photo</div>
                        @endif
                    </div>
                </td>
                <td class="info-wrap">
                    <div class="name">{{ strtoupper((string) ($user?->name ?? '-')) }}</div>
                    <div class="role-pill">{{ strtoupper((string) ($user?->role ?? 'user')) }}</div>

                    <div class="field">
                        <span class="field-label">{{ $primaryInfoLabel }}</span>
                        <span class="field-value">{{ $identityNumber ?? '-' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ $secondaryInfoLabel }}</span>
                        <span class="field-value">{{ $secondaryInfoValue }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ $tertiaryInfoLabel }}</span>
                        <span class="field-value">{{ $tertiaryInfoValue }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="bottom-band">
        <table>
            <tr>
                <td>
                    <span class="field-label">Level</span>
                    <span class="field-value">{{ $displayLevel ?: '-' }}</span>
                </td>
                <td>
                    <span class="field-label">Status</span>
                    <span class="field-value">ACTIVE</span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="page">
    <div class="corner-primary"></div>
    <div class="corner-accent"></div>

    <div class="back-top">
        <div class="title" style="margin-left:60px; margin-right:14px;">{{ $schoolName }}</div>
    </div>

    <div class="back-card">
        <div class="back-heading">School Information</div>

        <div class="contact-row">
            <strong>Address</strong>
            <span>{{ $contactAddress ?: '-' }}</span>
        </div>
        <div class="contact-row">
            <strong>Email</strong>
            <span>{{ $contactEmail ?: '-' }}</span>
        </div>
        <div class="contact-row">
            <strong>Phone Number</strong>
            <span>{{ $contactPhone ?: '-' }}</span>
        </div>
        <div class="contact-row">
            <strong>Website</strong>
            <span>{{ $websiteUrl ?: '-' }}</span>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line"></div>
                    <div class="sig-title">Signature Authority</div>
                </td>
                <td>
                    <div class="sig-line"></div>
                    <div class="sig-title">Principal</div>
                    <div class="sig-name">{{ strtoupper((string) ($principalName ?? 'Principal')) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="back-logo">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="School Logo">
        @else
            <span class="logo-fallback">{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
        @endif
    </div>
</div>
</body>
</html>
