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
            background: #edf2f8;
            overflow: hidden;
        }
        .sheet-table {
            width: 306pt;
            height: 243pt;
            border-collapse: separate;
            border-spacing: 10pt 8pt;
            table-layout: fixed;
        }
        .sheet-table td {
            width: 143pt;
            height: 227pt;
            vertical-align: top;
            padding: 0;
        }
        .card-face {
            position: relative;
            width: 143pt;
            height: 227pt;
            background: #ffffff;
            border: 0.8pt solid #dbe3ef;
            border-radius: 10pt;
            overflow: hidden;
        }
        .front-top-band,
        .back-top-band {
            position: absolute;
            left: 0;
            right: 0;
            background: {{ $primaryColor ?? '#153f8a' }};
            color: #ffffff;
        }
        .front-top-band {
            top: 0;
            height: 14pt;
        }
        .front-arch-primary {
            position: absolute;
            top: -24pt;
            left: -8pt;
            width: 159pt;
            height: 98pt;
            border-radius: 0 0 84pt 84pt;
            background: {{ $primaryColor ?? '#153f8a' }};
        }
        .front-arch-accent {
            position: absolute;
            top: -15pt;
            left: -4pt;
            width: 151pt;
            height: 91pt;
            border-radius: 0 0 80pt 80pt;
            border: 3pt solid {{ $accentColor ?? '#d39b2f' }};
            box-sizing: border-box;
        }
        .front-content {
            position: absolute;
            inset: 0;
            z-index: 2;
        }
        .front-logo {
            position: absolute;
            top: 12pt;
            left: 0;
            right: 0;
            text-align: center;
        }
        .front-logo img {
            max-width: 30pt;
            max-height: 30pt;
        }
        .logo-fallback {
            display: inline-block;
            width: 30pt;
            height: 30pt;
            line-height: 30pt;
            border-radius: 50%;
            background: rgba(255,255,255,0.14);
            color: #ffffff;
            font-size: 10pt;
            font-weight: 700;
        }
        .school-name {
            position: absolute;
            top: 44pt;
            left: 10pt;
            right: 10pt;
            text-align: center;
            font-size: 7.8pt;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: 0.25pt;
            color: {{ $primaryColor ?? '#153f8a' }};
            text-transform: uppercase;
        }
        .motto-line {
            position: absolute;
            top: 65pt;
            left: 18pt;
            right: 18pt;
            text-align: center;
            color: {{ $accentColor ?? '#d39b2f' }};
            font-size: 4.2pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35pt;
        }
        .motto-line:before,
        .motto-line:after {
            content: "";
            display: inline-block;
            width: 26pt;
            height: 0.8pt;
            vertical-align: middle;
            background: {{ $primaryColor ?? '#153f8a' }};
            margin: 0 4pt;
        }
        .photo-ring {
            position: absolute;
            top: 78pt;
            left: 29.5pt;
            width: 84pt;
            height: 84pt;
            border-radius: 50%;
            border: 3pt solid {{ $primaryColor ?? '#153f8a' }};
            background: #ffffff;
            box-sizing: border-box;
            overflow: hidden;
        }
        .photo-ring img {
            width: 78pt;
            height: 78pt;
            margin: 0;
            display: block;
        }
        .photo-placeholder {
            padding-top: 30pt;
            text-align: center;
            font-size: 5pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .info-panel {
            position: absolute;
            left: 10pt;
            right: 10pt;
            top: 168pt;
            bottom: 34pt;
        }
        .info-accent {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3pt;
            border-radius: 999pt;
            background: {{ $accentColor ?? '#d39b2f' }};
        }
        .info-list {
            margin-left: 8pt;
        }
        .info-row {
            padding: 2.6pt 0 3pt;
            border-bottom: 0.8pt dotted #cdd7e5;
        }
        .info-label {
            display: inline-block;
            width: 36pt;
            font-size: 4.4pt;
            color: #475569;
            text-transform: uppercase;
        }
        .info-value {
            display: inline-block;
            width: 78pt;
            font-size: 5.8pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            vertical-align: top;
            text-transform: uppercase;
            word-break: break-word;
        }
        .front-footer {
            bottom: 0;
            height: 30pt;
            padding: 4pt 10pt 0;
            box-sizing: border-box;
            background: #ffffff;
            border-top: 1pt solid #dbe3ef;
        }
        .session-table {
            width: 100%;
            border-collapse: collapse;
            color: {{ $primaryColor ?? '#153f8a' }};
        }
        .session-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .session-table td + td {
            border-left: 0.8pt solid #dbe3ef;
        }
        .session-label {
            display: block;
            font-size: 4.1pt;
            text-transform: uppercase;
            letter-spacing: 0.28pt;
            color: #475569;
        }
        .session-value {
            display: block;
            margin-top: 2pt;
            font-size: 6.2pt;
            font-weight: 700;
            line-height: 1.15;
            color: {{ $primaryColor ?? '#153f8a' }};
        }
        .back-top-band {
            top: 0;
            height: 22pt;
            text-align: center;
            padding-top: 5pt;
            box-sizing: border-box;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35pt;
        }
        .back-body {
            position: absolute;
            inset: 26pt 9pt 10pt;
            z-index: 2;
        }
        .watermark {
            position: absolute;
            top: 34pt;
            left: 50%;
            width: 78pt;
            height: 78pt;
            margin-left: -39pt;
            opacity: 0.08;
            text-align: center;
            z-index: 1;
        }
        .watermark img {
            max-width: 78pt;
            max-height: 78pt;
        }
        .terms-list {
            margin: 0 0 10pt 0;
            padding: 0;
            list-style: none;
        }
        .terms-item {
            margin-bottom: 5pt;
            font-size: 5pt;
            line-height: 1.45;
            color: #1f2937;
        }
        .terms-dot {
            display: inline-block;
            width: 7pt;
            color: {{ $primaryColor ?? '#153f8a' }};
            font-weight: 700;
        }
        .section-divider {
            height: 1pt;
            background: {{ $primaryColor ?? '#153f8a' }};
            opacity: 0.9;
            margin: 8pt 0 7pt;
        }
        .authority-title {
            font-size: 4.8pt;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        .authority-name {
            margin-top: 2pt;
            font-size: 6.3pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#153f8a' }};
            text-transform: uppercase;
            line-height: 1.15;
        }
        .authority-signature {
            margin-top: 6pt;
            height: 28pt;
            text-align: left;
        }
        .authority-signature img {
            max-width: 82pt;
            max-height: 24pt;
            object-fit: contain;
        }
        .authority-sign-line {
            width: 88pt;
            border-top: 0.8pt solid rgba(15, 23, 42, 0.38);
            margin-top: 2pt;
        }
        .authority-sign-label {
            width: 88pt;
            margin-top: 2pt;
            font-size: 4pt;
            color: #475569;
            text-align: center;
            text-transform: uppercase;
        }
        .bottom-grid {
            margin-top: 8pt;
            width: 100%;
            border-collapse: collapse;
        }
        .bottom-grid td {
            vertical-align: top;
            width: 50%;
        }
        .bottom-grid.student td + td {
            border-left: 0.8pt solid #d4dce8;
            padding-left: 6pt;
        }
        .bottom-grid.student td:first-child {
            padding-right: 6pt;
        }
        .contact-box,
        .emergency-box {
            min-height: 56pt;
        }
        .block-heading {
            font-size: 5.2pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#153f8a' }};
            text-transform: uppercase;
            letter-spacing: 0.25pt;
            margin-bottom: 5pt;
        }
        .detail-row {
            margin-bottom: 4pt;
        }
        .detail-label {
            display: block;
            font-size: 4.1pt;
            color: #475569;
            text-transform: uppercase;
        }
        .detail-value {
            display: block;
            margin-top: 1pt;
            font-size: 5pt;
            line-height: 1.35;
            color: #111827;
            font-weight: 700;
            word-break: break-word;
        }
        .school-contact-center {
            margin-top: 8pt;
            text-align: center;
        }
        .school-contact-center .detail-row {
            margin-bottom: 5pt;
        }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL NAME'));
    $motto = trim((string) ($schoolMotto ?? ''));
    $logoFallback = collect(explode(' ', (string) ($school?->name ?? 'SC')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $isStudent = (string) ($user?->role ?? '') === 'student';
    $displayGender = strtoupper(trim((string) ($student?->sex ?? $staff?->sex ?? '-')));
    $frontThirdLabel = $isStudent ? 'Class' : 'Role';
    $frontThirdValue = $isStudent
        ? trim((string) ($displayClass ?? $displayLevel ?? '-'))
        : trim((string) ($displayPosition ?? $displayDepartment ?? $displayClass ?? 'STAFF'));
    $guardianNameValue = trim((string) ($guardianName ?? ''));
    $guardianPhoneValue = trim((string) ($guardianPhone ?? ''));
    $guardianRelationshipValue = trim((string) ($guardianRelationship ?? ''));
    $showStudentEmergency = $isStudent && ($guardianNameValue !== '' || $guardianPhoneValue !== '' || $guardianRelationshipValue !== '');
    $issueSessionDisplay = trim((string) ($issueSessionLabel ?? ''));
    $expirySessionDisplay = trim((string) ($expirySessionLabel ?? ''));
    $issueSessionDisplay = $issueSessionDisplay !== '' ? $issueSessionDisplay : '-';
    $expirySessionDisplay = $expirySessionDisplay !== '' ? $expirySessionDisplay : '-';
    $bullets = [
        $isStudent
            ? 'This card identifies the student and must be presented on request.'
            : 'This card identifies the staff member and must be presented on request.',
        'Replacement is required if the card is damaged or lost.',
        'Unauthorized use of this card is not allowed.',
    ];
@endphp

<div class="sheet">
    <table class="sheet-table">
        <tr>
            <td>
                <div class="card-face">
                    <div class="front-top-band"></div>
                    <div class="front-arch-primary"></div>
                    <div class="front-arch-accent"></div>

                    <div class="front-content">
                        <div class="front-logo">
                            @if(!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="School Logo">
                            @else
                                <span class="logo-fallback">{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
                            @endif
                        </div>

                        <div class="school-name">{{ $schoolName }}</div>

                        @if($motto !== '')
                            <div class="motto-line">{{ strtoupper($motto) }}</div>
                        @endif

                        <div class="photo-ring">
                            @if(!empty($userPhotoDataUri))
                                <img src="{{ $userPhotoDataUri }}" alt="User Photo">
                            @else
                                <div class="photo-placeholder">Photo</div>
                            @endif
                        </div>

                        <div class="info-panel">
                            <div class="info-accent"></div>
                            <div class="info-list">
                                <div class="info-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-value">{{ strtoupper((string) ($user?->name ?? '-')) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $isStudent ? 'ID Number' : 'Staff ID' }}</span>
                                    <span class="info-value">{{ strtoupper((string) ($identityNumber ?? '-')) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $frontThirdLabel }}</span>
                                    <span class="info-value">{{ strtoupper($frontThirdValue !== '' ? $frontThirdValue : '-') }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value">{{ $displayGender !== '' ? $displayGender : '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="front-footer">
                            <table class="session-table">
                                <tr>
                                    <td>
                                        <span class="session-label">Issue Session</span>
                                        <span class="session-value">{{ $issueSessionDisplay }}</span>
                                    </td>
                                    <td>
                                        <span class="session-label">Expiry Session</span>
                                        <span class="session-value">{{ $expirySessionDisplay }}</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="card-face">
                    <div class="back-top-band">Terms &amp; Conditions</div>
                    @if(!empty($logoDataUri))
                        <div class="watermark">
                            <img src="{{ $logoDataUri }}" alt="">
                        </div>
                    @endif

                    <div class="back-body">
                        <div class="terms-list">
                            @foreach($bullets as $bullet)
                                <div class="terms-item">
                                    <span class="terms-dot">&#8226;</span>{{ $bullet }}
                                </div>
                            @endforeach
                        </div>

                        <div class="section-divider"></div>

                        <div class="authority-title">School Authority</div>
                        <div class="detail-label" style="margin-top: 3pt;">Head of School</div>
                        <div class="authority-name">{{ strtoupper((string) ($principalName ?? 'HEAD OF SCHOOL')) }}</div>

                        @if(!empty($headSignatureDataUri))
                            <div class="authority-signature">
                                <img src="{{ $headSignatureDataUri }}" alt="Head Signature">
                                <div class="authority-sign-line"></div>
                                <div class="authority-sign-label">Authorized Signature</div>
                            </div>
                        @endif

                        <div class="section-divider"></div>

                        @if($showStudentEmergency)
                            <table class="bottom-grid student">
                                <tr>
                                    <td>
                                        <div class="contact-box">
                                            <div class="block-heading">School Contact</div>
                                            <div class="detail-row">
                                                <span class="detail-label">Address</span>
                                                <span class="detail-value">{{ $contactAddress }}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Email</span>
                                                <span class="detail-value">{{ $contactEmail }}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Phone</span>
                                                <span class="detail-value">{{ $contactPhone }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="emergency-box">
                                            <div class="block-heading">Guardian Contact</div>
                                            <div class="detail-row">
                                                <span class="detail-label">Name</span>
                                                <span class="detail-value">{{ strtoupper($guardianNameValue !== '' ? $guardianNameValue : '-') }}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Phone</span>
                                                <span class="detail-value">{{ $guardianPhoneValue !== '' ? $guardianPhoneValue : '-' }}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Relationship</span>
                                                <span class="detail-value">{{ strtoupper($guardianRelationshipValue !== '' ? $guardianRelationshipValue : '-') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <div class="school-contact-center">
                                <div class="block-heading">School Contact</div>
                                <div class="detail-row">
                                    <span class="detail-label">Address</span>
                                    <span class="detail-value">{{ $contactAddress }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value">{{ $contactEmail }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value">{{ $contactPhone }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
