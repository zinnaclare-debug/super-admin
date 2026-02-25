<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolFeePayment;
use App\Models\SchoolFeeSetting;
use App\Models\Student;
use App\Models\Term;
use Carbon\Carbon;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
        $setting = $this->resolveFeeSetting(
            $schoolId,
            (int) $session->id,
            (int) $term->id,
            $studentLevel
        );

        $amountDue = (float) ($setting?->amount_due ?? 0);
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
                'fee' => [
                    'student_level' => $studentLevel,
                    'configured_level' => $setting?->level,
                    'amount_due' => $amountDue,
                    'total_paid' => $totalPaid,
                    'outstanding' => $outstanding,
                    'is_fully_paid' => $outstanding <= 0.00001,
                ],
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
        $setting = $this->resolveFeeSetting(
            $schoolId,
            (int) $session->id,
            (int) $term->id,
            $studentLevel
        );

        $amountDue = (float) ($setting?->amount_due ?? 0);
        if ($amountDue <= 0) {
            $message = $studentLevel
                ? "School fees have not been configured for {$studentLevel} level yet."
                : 'School fees have not been configured yet.';
            return response()->json(['message' => $message], 422);
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
}
