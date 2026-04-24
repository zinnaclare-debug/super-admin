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
            width: 306pt;
            height: 243pt;
        }
        .sheet {
            position: relative;
            width: 306pt;
            height: 243pt;
            background: #eef3f8;
            overflow: hidden;
        }
        .card-face {
            position: absolute;
            top: 8pt;
            width: 143pt;
            height: 227pt;
            background: #ffffff;
            border: 0.8pt solid #dbe3ef;
            border-radius: 10pt;
            overflow: hidden;
            box-sizing: border-box;
        }
        .front-face {
            left: 8pt;
        }
        .back-face {
            left: 155pt;
        }
        .front-accent {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6pt;
            background: {{ $primaryColor ?? '#153f8a' }};
        }
        .front-logo {
            position: absolute;
            top: 12pt;
            left: 0;
            right: 0;
            text-align: center;
        }
        .front-logo img {
            max-width: 28pt;
            max-height: 28pt;
        }
        .logo-fallback {
            display: inline-block;
            width: 28pt;
            height: 28pt;
            line-height: 28pt;
            border-radius: 50%;
            background: {{ $primaryColor ?? '#153f8a' }};
            color: #ffffff;
            font-size: 10pt;
            font-weight: 700;
            text-align: center;
        }
        .school-name-wrap {
            position: absolute;
            top: 44pt;
            left: 9pt;
            right: 9pt;
            padding: 4pt 3pt 5pt;
            text-align: center;
            border-top: 1pt solid {{ $primaryColor ?? '#153f8a' }};
            border-bottom: 1pt solid {{ $primaryColor ?? '#153f8a' }};
            background: #ffffff;
        }
        .school-name {
            margin: 0;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: 0.08pt;
            text-transform: uppercase;
            color: #0f172a;
        }
        .school-motto {
            margin-top: 2pt;
            font-size: 3.6pt;
            line-height: 1.2;
            text-transform: uppercase;
            color: {{ $accentColor ?? '#d39b2f' }};
            letter-spacing: 0.2pt;
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
            overflow: hidden;
            box-sizing: border-box;
        }
        .photo-ring img {
            display: block;
            width: 78pt;
            height: 78pt;
        }
        .photo-placeholder {
            padding-top: 30pt;
            text-align: center;
            font-size: 5pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .front-info {
            position: absolute;
            left: 10pt;
            right: 10pt;
            top: 162pt;
            bottom: 8pt;
        }
        .info-rail {
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
            padding: 1.6pt 0;
            border-bottom: 0.8pt dotted #d1d9e6;
        }
        .info-label {
            display: inline-block;
            width: 29pt;
            font-size: 3.7pt;
            color: #475569;
            text-transform: uppercase;
            vertical-align: top;
        }
        .info-value {
            display: inline-block;
            width: 87pt;
            font-size: 4.15pt;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
            text-transform: uppercase;
            vertical-align: top;
            word-break: break-word;
        }
        .back-top-band {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 22pt;
            background: {{ $primaryColor ?? '#153f8a' }};
            color: #ffffff;
            text-align: center;
            padding-top: 5pt;
            box-sizing: border-box;
            font-size: 7.6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        .watermark {
            position: absolute;
            top: 34pt;
            left: 50%;
            width: 74pt;
            height: 74pt;
            margin-left: -37pt;
            opacity: 0.08;
            text-align: center;
            z-index: 1;
        }
        .watermark img {
            max-width: 74pt;
            max-height: 74pt;
        }
        .back-body {
            position: absolute;
            top: 28pt;
            left: 9pt;
            right: 9pt;
            bottom: 8pt;
            z-index: 2;
        }
        .terms-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .terms-item {
            margin-bottom: 5pt;
            font-size: 4.5pt;
            line-height: 1.35;
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
            margin: 6pt 0 6pt;
            background: {{ $primaryColor ?? '#153f8a' }};
        }
        .authority-title {
            font-size: 4.7pt;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.25pt;
        }
        .authority-name {
            margin-top: 2pt;
            font-size: 6pt;
            font-weight: 700;
            line-height: 1.15;
            text-transform: uppercase;
            color: {{ $primaryColor ?? '#153f8a' }};
        }
        .authority-signature {
            margin-top: 4pt;
            height: 22pt;
        }
        .authority-signature img {
            max-width: 82pt;
            max-height: 17pt;
            object-fit: contain;
        }
        .authority-sign-line {
            width: 86pt;
            margin-top: 2pt;
            border-top: 0.8pt solid rgba(15, 23, 42, 0.38);
        }
        .authority-sign-label {
            width: 86pt;
            margin-top: 2pt;
            font-size: 3.8pt;
            color: #475569;
            text-transform: uppercase;
            text-align: left;
        }
        .bottom-grid {
            margin-top: 5pt;
            width: 100%;
            border-collapse: collapse;
        }
        .bottom-grid td {
            vertical-align: top;
        }
        .bottom-grid.student td:first-child {
            width: 58%;
            padding-right: 4pt;
        }
        .bottom-grid.student td + td {
            width: 42%;
            padding-left: 4pt;
            border-left: 0.8pt solid #dbe3ef;
        }
        .block-heading {
            font-size: 4.4pt;
            font-weight: 700;
            color: {{ $primaryColor ?? '#153f8a' }};
            text-transform: uppercase;
            letter-spacing: 0.2pt;
            margin-bottom: 2pt;
        }
        .detail-row {
            margin-bottom: 2pt;
        }
        .detail-label {
            display: block;
            font-size: 3.5pt;
            color: #475569;
            text-transform: uppercase;
        }
        .detail-value {
            display: block;
            margin-top: 1pt;
            font-size: 3.85pt;
            font-weight: 700;
            line-height: 1.12;
            color: #111827;
            word-break: break-word;
        }
        .contact-validity-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .contact-validity-grid td {
            width: 50%;
            vertical-align: top;
        }
        .contact-validity-grid td:first-child {
            padding-right: 4pt;
        }
        .contact-validity-grid td + td {
            padding-left: 4pt;
            border-left: 0.8pt solid #dbe3ef;
        }
        .school-contact-center {
            margin-top: 8pt;
            text-align: center;
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
    $departmentValue = trim((string) ($displayDepartment ?? ''));
    $showDepartment = $departmentValue !== '' && strtolower($departmentValue) !== strtolower($frontThirdValue);
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
    <div class="card-face front-face">
        <div class="front-accent"></div>

        <div class="front-logo">
            @if(!empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="School Logo">
            @else
                <span class="logo-fallback">{{ $logoFallback !== '' ? $logoFallback : 'SC' }}</span>
            @endif
        </div>

        <div class="school-name-wrap">
            <p class="school-name">{{ $schoolName }}</p>
            @if($motto !== '')
                <div class="school-motto">{{ strtoupper($motto) }}</div>
            @endif
        </div>

        <div class="photo-ring">
            @if(!empty($userPhotoDataUri))
                <img src="{{ $userPhotoDataUri }}" alt="User Photo">
            @else
                <div class="photo-placeholder">Photo</div>
            @endif
        </div>

        <div class="front-info">
            <div class="info-rail"></div>
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
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $displayGender !== '' ? $displayGender : '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ $frontThirdLabel }}</span>
                    <span class="info-value">{{ strtoupper($frontThirdValue !== '' ? $frontThirdValue : '-') }}</span>
                </div>
                @if($showDepartment)
                    <div class="info-row">
                        <span class="info-label">Department</span>
                        <span class="info-value">{{ strtoupper($departmentValue) }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Issue Session</span>
                    <span class="info-value">{{ strtoupper($issueSessionDisplay) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Expiry Session</span>
                    <span class="info-value">{{ strtoupper($expirySessionDisplay) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-face back-face">
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
                            <div class="block-heading">School Contact</div>
                            <table class="contact-validity-grid">
                                <tr>
                                    <td>
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
                                    </td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                        <td>
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
</div>
</body>
</html>
