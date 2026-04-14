<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolFeePayment;
use App\Models\SchoolFeeSetting;
use App\Models\Student;
use App\Models\StudentFeePlan;
use App\Models\Term;
use Carbon\Carbon;
use App\Models\Enrollment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolFeesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured for your school.',
            ], 422);
        }

        $studentLevel = $this->resolveStudentLevel(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id
        );
        $feeSource = $this->resolveStudentFeeSource(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id,
            $studentLevel
        );
        $amountDue = (float) $feeSource['amount_due'];
        $totalPaid = (float) SchoolFeePayment::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', 'success')
            ->sum('amount_paid');
        $outstanding = max($amountDue - $totalPaid, 0);

        $payments = SchoolFeePayment::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'amount_paid' => (float) $p->amount_paid,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'paid_at' => optional($p->paid_at)?->toDateTimeString(),
                    'created_at' => optional($p->created_at)?->toDateTimeString(),
                    'failure_reason' => $p->status === 'success'
                        ? null
                        : ($p->paystack_gateway_response ?: $p->paystack_status ?: 'Payment unsuccessful'),
                    'receipt_download_url' => '/api/student/school-fees/payments/' . $p->id . '/receipt',
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                ],
                'fee' => array_merge([
                    'student_level' => $studentLevel,
                    'configured_level' => $feeSource['configured_level'],
                    'amount_due' => $amountDue,
                    'total_paid' => $totalPaid,
                    'outstanding' => $outstanding,
                    'source' => $feeSource['source'],
                    'line_items' => $feeSource['line_items'],
                    'invoice_download_url' => '/api/student/school-fees/invoice',
                ], $this->buildFeeStatusPayload($amountDue, $totalPaid, $outstanding)),
                'student' => [
                    'id' => $student->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'payments' => $payments,
            ],
        ]);
    }

    public function initialize(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured for your school.',
            ], 422);
        }

        $studentLevel = $this->resolveStudentLevel(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id
        );
        $feeSource = $this->resolveStudentFeeSource(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id,
            $studentLevel
        );
        $amountDue = (float) $feeSource['amount_due'];
        if ($amountDue <= 0) {
            return response()->json([
                'message' => 'School fees online payment has not been inputed for this term. Please contact your school admin.',
            ], 422);
        }

        $totalPaid = (float) SchoolFeePayment::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', 'success')
            ->sum('amount_paid');
        $outstanding = max($amountDue - $totalPaid, 0);
        if ($outstanding <= 0.00001) {
            return response()->json(['message' => 'School fees already fully paid.'], 422);
        }

        $payload = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $requestedAmount = round((float) $payload['amount'], 2);
        if ($requestedAmount > $outstanding) {
            return response()->json([
                'message' => 'Amount cannot exceed outstanding balance.',
                'outstanding' => $outstanding,
            ], 422);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }
        $school = School::where('id', $schoolId)->first();

        $reference = 'SFP-' . $schoolId . '-' . $student->id . '-' . Str::upper(Str::random(12));
        $callbackUrl = (string) config('services.paystack.callback_url');
        if ($callbackUrl === '') {
            $callbackUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/student/school-fees';
        }

        $initializePayload = [
            'email' => $user->email,
            'amount' => (int) round($requestedAmount * 100),
            'reference' => $reference,
            'channels' => ['card', 'bank', 'ussd', 'qr', 'bank_transfer'],
            'callback_url' => $callbackUrl,
            'metadata' => [
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'student_user_id' => $user->id,
                'student_username' => $user->username,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
            ],
        ];
        if (!empty($school?->paystack_subaccount_code)) {
            $initializePayload['subaccount'] = $school->paystack_subaccount_code;
        }

        $initRes = Http::withToken($secret)
            ->timeout(20)
            ->post(rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/initialize', $initializePayload);

        $json = $initRes->json();
        if (!$initRes->successful() || !($json['status'] ?? false)) {
            return response()->json([
                'message' => $json['message'] ?? 'Failed to initialize payment with Paystack.',
            ], 502);
        }

        $authUrl = (string) data_get($json, 'data.authorization_url', '');
        $accessCode = (string) data_get($json, 'data.access_code', '');
        if ($authUrl === '' || $accessCode === '') {
            return response()->json(['message' => 'Invalid response from Paystack.'], 502);
        }

        SchoolFeePayment::create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'student_user_id' => $user->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'amount_due_snapshot' => $amountDue,
            'amount_paid' => $requestedAmount,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => $reference,
            'paystack_access_code' => $accessCode,
            'paystack_authorization_url' => $authUrl,
            'meta' => [
                'student_name' => $user->name,
                'student_email' => $user->email,
                'student_username' => $user->username,
                'fee_source' => $feeSource['source'],
                'fee_line_items' => $feeSource['line_items'],
            ],
        ]);

        return response()->json([
            'data' => [
                'reference' => $reference,
                'authorization_url' => $authUrl,
            ],
        ], 201);
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $payload = $request->validate([
            'reference' => 'required|string',
        ]);

        $payment = SchoolFeePayment::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('student_user_id', $user->id)
            ->where('reference', $payload['reference'])
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        }

        if ($payment->status === 'success') {
            return response()->json([
                'data' => [
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                    'amount_paid' => (float) $payment->amount_paid,
                ],
            ]);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }

        $verifyRes = Http::withToken($secret)
            ->timeout(20)
            ->get(rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/verify/' . urlencode($payment->reference));

        $json = $verifyRes->json();
        if (!$verifyRes->successful() || !($json['status'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'paystack_status' => (string) data_get($json, 'data.status', 'failed'),
                'paystack_gateway_response' => (string) ($json['message'] ?? 'verification_failed'),
            ]);

            return response()->json([
                'message' => $json['message'] ?? 'Failed to verify payment.',
            ], 502);
        }

        $paystackStatus = (string) data_get($json, 'data.status', '');
        $gatewayResponse = (string) data_get($json, 'data.gateway_response', '');
        $channel = (string) data_get($json, 'data.channel', '');
        $amountKobo = (int) data_get($json, 'data.amount', 0);
        $amountPaid = round($amountKobo / 100, 2);

        if ($paystackStatus !== 'success') {
            $payment->update([
                'status' => 'failed',
                'paystack_status' => $paystackStatus,
                'paystack_gateway_response' => $gatewayResponse,
                'paystack_channel' => $channel ?: null,
            ]);

            return response()->json([
                'message' => 'Payment not successful yet.',
                'data' => [
                    'reference' => $payment->reference,
                    'status' => 'failed',
                ],
            ], 422);
        }

        if (abs($amountPaid - (float) $payment->amount_paid) > 0.01) {
            return response()->json([
                'message' => 'Amount mismatch detected during verification.',
            ], 422);
        }

        $paidAt = data_get($json, 'data.paid_at')
            ? Carbon::parse((string) data_get($json, 'data.paid_at'))
            : now();
        $payment->update([
            'status' => 'success',
            'paystack_status' => $paystackStatus,
            'paystack_gateway_response' => $gatewayResponse,
            'paystack_channel' => $channel ?: null,
            'paid_at' => $paidAt,
        ]);

        return response()->json([
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status,
                'amount_paid' => (float) $payment->amount_paid,
            ],
        ]);
    }

    public function invoice(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $user->id)
            ->firstOrFail();

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured for your school.',
            ], 422);
        }

        $school = School::query()->find($schoolId);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $studentLevel = $this->resolveStudentLevel(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id
        );
        $feeSource = $this->resolveStudentFeeSource(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id,
            $studentLevel
        );
        $amountDue = (float) $feeSource['amount_due'];
        if ($amountDue <= 0) {
            return response()->json([
                'message' => 'School fees online payment has not been inputed for this term. Please contact your school admin.',
            ], 422);
        }

        $lineItems = $feeSource['line_items'];
        if (empty($lineItems)) {
            $lineItems = [[
                'description' => 'School Fees',
                'amount' => $amountDue,
            ]];
        }

        $totalPaid = (float) SchoolFeePayment::query()
            ->where('school_id', $schoolId)
            ->where('student_id', (int) $student->id)
            ->where('academic_session_id', (int) $session->id)
            ->where('term_id', (int) $term->id)
            ->where('status', 'success')
            ->sum('amount_paid');
        $outstanding = max($amountDue - $totalPaid, 0);
        $status = $this->buildFeeStatusPayload($amountDue, $totalPaid, $outstanding);

        $placement = $this->resolveStudentPlacement((int) $student->id, (int) $term->id);
        $logoDataUri = $this->logoDataUri($school->logo_path);
        $headSignatureDataUri = $this->imageDataUri($school->head_signature_path);
        $invoiceNumber = 'INV-' . $schoolId . '-' . $student->id . '-' . $session->id . '-' . $term->id;

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.school_fee_invoice', [
                'school' => $school,
                'logoDataUri' => $logoDataUri,
                'headSignatureDataUri' => $headSignatureDataUri,
                'studentUser' => $user,
                'student' => $student,
                'studentLevel' => $studentLevel,
                'className' => $placement['class_name'],
                'departmentName' => $placement['department_name'],
                'session' => $session,
                'term' => $term,
                'lineItems' => $lineItems,
                'amountDue' => $amountDue,
                'totalPaid' => $totalPaid,
                'outstanding' => $outstanding,
                'statusLabel' => $status['payment_status_label'],
                'statusMessage' => $status['status_message'],
                'invoiceNumber' => $invoiceNumber,
                'generatedAt' => Date::now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'school_fee_invoice_' . $session->id . '_' . $term->id . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate school fee invoice PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function receipt(Request $request, SchoolFeePayment $payment)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $user->id)
            ->firstOrFail();

        if (
            (int) $payment->school_id !== $schoolId
            || (int) $payment->student_id !== (int) $student->id
            || (int) $payment->student_user_id !== (int) $user->id
        ) {
            return response()->json(['message' => 'Receipt not found.'], 404);
        }
        if ((string) $payment->status !== 'success') {
            return response()->json(['message' => 'Receipt is available only for successful payments.'], 422);
        }

        $school = School::query()->find($schoolId);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $session = AcademicSession::query()->find((int) $payment->academic_session_id);
        $term = Term::query()->find((int) $payment->term_id);

        $studentLevel = $this->resolveStudentLevel(
            $schoolId,
            (int) $student->id,
            (int) $payment->academic_session_id,
            (int) $payment->term_id
        );
        $placement = $this->resolveStudentPlacement((int) $student->id, (int) $payment->term_id);

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $lineItems = $this->normalizeLineItems((array) ($meta['fee_line_items'] ?? []));
        if (empty($lineItems)) {
            $feeSource = $this->resolveStudentFeeSource(
                $schoolId,
                (int) $student->id,
                (int) $payment->academic_session_id,
                (int) $payment->term_id,
                $studentLevel
            );
            $lineItems = $feeSource['line_items'];
            if (empty($lineItems)) {
                $lineItems = [[
                    'description' => 'School Fees',
                    'amount' => (float) $payment->amount_due_snapshot,
                ]];
            }
        }

        $totalPaid = (float) SchoolFeePayment::query()
            ->where('school_id', $schoolId)
            ->where('student_id', (int) $student->id)
            ->where('academic_session_id', (int) $payment->academic_session_id)
            ->where('term_id', (int) $payment->term_id)
            ->where('status', 'success')
            ->sum('amount_paid');
        $amountDue = (float) $payment->amount_due_snapshot;
        $outstanding = max($amountDue - $totalPaid, 0);

        $headSignatureDataUri = $this->imageDataUri($school->head_signature_path);
        $logoDataUri = $this->logoDataUri($school->logo_path);

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.school_fee_receipt', [
                'school' => $school,
                'headSignatureDataUri' => $headSignatureDataUri,
                'logoDataUri' => $logoDataUri,
                'studentUser' => $user,
                'student' => $student,
                'studentLevel' => $studentLevel,
                'className' => $placement['class_name'],
                'departmentName' => $placement['department_name'],
                'session' => $session,
                'term' => $term,
                'payment' => $payment,
                'lineItems' => $lineItems,
                'amountDue' => $amountDue,
                'totalPaid' => $totalPaid,
                'outstanding' => $outstanding,
                'generatedAt' => Date::now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $safeReference = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $payment->reference);
            $filename = 'fee_receipt_' . ($safeReference ?: $payment->id) . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate receipt PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildFeeStatusPayload(float $amountDue, float $totalPaid, float $outstanding): array
    {
        if ($amountDue <= 0.00001) {
            return [
                'has_invoice' => false,
                'can_pay' => false,
                'is_fully_paid' => false,
                'payment_made' => false,
                'payment_status' => 'awaiting_invoice',
                'payment_status_label' => 'Awaiting Invoice',
                'status_message' => 'School fees online payment has not been inputed for this term. Please contact your school admin.',
            ];
        }

        if ($outstanding <= 0.00001 && $totalPaid > 0.00001) {
            return [
                'has_invoice' => true,
                'can_pay' => false,
                'is_fully_paid' => true,
                'payment_made' => true,
                'payment_status' => 'paid',
                'payment_status_label' => 'Payment Made',
                'status_message' => 'School fees payment has been completed for this term.',
            ];
        }

        if ($totalPaid > 0.00001) {
            return [
                'has_invoice' => true,
                'can_pay' => true,
                'is_fully_paid' => false,
                'payment_made' => true,
                'payment_status' => 'partially_paid',
                'payment_status_label' => 'Partially Paid',
                'status_message' => 'Part payment has been received. Outstanding balance is still pending.',
            ];
        }

        return [
            'has_invoice' => true,
            'can_pay' => true,
            'is_fully_paid' => false,
            'payment_made' => false,
            'payment_status' => 'payment_pending',
            'payment_status_label' => 'Payment Pending',
            'status_message' => 'Invoice is available for this term. No payment has been made yet.',
        ];
    }

    private function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            return [null, null];
        }

        $term = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();
        if (!$term) {
            $term = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }

    private function resolveFeeSetting(int $schoolId, int $sessionId, int $termId, ?string $studentLevel): ?SchoolFeeSetting
    {
        $base = SchoolFeeSetting::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId);

        if ($studentLevel) {
            $match = (clone $base)
                ->where('level', $studentLevel)
                ->first();
            if ($match) {
                return $match;
            }
        }

        $legacy = (clone $base)
            ->whereNull('level')
            ->first();
        if ($legacy) {
            return $legacy;
        }

        if (!$studentLevel) {
            return (clone $base)->orderBy('id')->first();
        }

        return null;
    }

    private function resolveStudentLevel(int $schoolId, int $studentId, int $sessionId, int $termId): ?string
    {
        $level = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                $q->where('enrollments.school_id', $schoolId);
            })
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->where('classes.school_id', $schoolId)
            ->orderByDesc('enrollments.id')
            ->value('classes.level');

        if (!$level) {
            $level = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->where('class_students.school_id', $schoolId)
                ->where('class_students.student_id', $studentId)
                ->where('class_students.academic_session_id', $sessionId)
                ->orderByDesc('class_students.id')
                ->value('classes.level');
        }

        $normalized = strtolower(trim((string) $level));
        return $normalized !== '' ? $normalized : null;
    }

    private function resolveStudentFeeSource(
        int $schoolId,
        int $studentId,
        int $sessionId,
        int $termId,
        ?string $studentLevel
    ): array {
        $plan = StudentFeePlan::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->first();

        if ($plan) {
            $lineItems = $this->normalizeLineItems((array) ($plan->line_items ?? []));

            return [
                'source' => 'student_plan',
                'amount_due' => (float) $plan->amount_due,
                'configured_level' => $studentLevel,
                'line_items' => $lineItems,
            ];
        }

        $setting = $this->resolveFeeSetting($schoolId, $sessionId, $termId, $studentLevel);
        $amountDue = (float) ($setting?->amount_due ?? 0);
        $lineItems = $amountDue > 0 ? [[
            'description' => 'School Fees',
            'amount' => $amountDue,
        ]] : [];

        return [
            'source' => 'level_setting',
            'amount_due' => $amountDue,
            'configured_level' => $setting?->level,
            'line_items' => $lineItems,
        ];
    }

    private function normalizeLineItems(array $items): array
    {
        return collect($items)
            ->map(function ($item, $index) {
                $enabledRaw = $item['enabled'] ?? true;
                $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($enabled === null) {
                    $enabled = true;
                }
                if (!$enabled) {
                    return null;
                }

                $description = trim((string) ($item['description'] ?? ''));
                $amountRaw = $item['amount'] ?? null;
                $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : null;

                if ($description === '' && ($amount === null || $amount <= 0)) {
                    return null;
                }

                if ($description === '') {
                    $description = 'Fee Item ' . ((int) $index + 1);
                }

                return [
                    'description' => $description,
                    'amount' => max((float) ($amount ?? 0), 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveStudentPlacement(int $studentId, int $termId): array
    {
        $placement = [
            'class_name' => null,
            'department_name' => null,
        ];

        $row = DB::table('enrollments')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->orderByDesc('enrollments.id')
            ->select([
                'classes.name as class_name',
                'class_departments.name as department_name',
            ])
            ->first();

        if ($row) {
            $placement['class_name'] = $row->class_name ? (string) $row->class_name : null;
            $placement['department_name'] = $row->department_name ? (string) $row->department_name : null;
        }

        return $placement;
    }

    private function logoDataUri(?string $logoPath): ?string
    {
        return $this->imageDataUri($logoPath);
    }

    private function imageDataUri(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        $mime = @mime_content_type($fullPath) ?: 'image/png';
        $data = @file_get_contents($fullPath);
        if ($data === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}




