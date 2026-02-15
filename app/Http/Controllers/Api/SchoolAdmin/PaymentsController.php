<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolFeePayment;
use App\Models\SchoolFeeSetting;
use App\Models\Term;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function config(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $setting = SchoolFeeSetting::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->first();
        $school = School::where('id', $schoolId)->first();

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
                'amount_due' => (float) ($setting?->amount_due ?? 0),
                'paystack_subaccount_code' => $school?->paystack_subaccount_code,
            ],
        ]);
    }

    public function upsertConfig(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $payload = $request->validate([
            'amount_due' => 'required|numeric|min:0',
            'paystack_subaccount_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        $setting = SchoolFeeSetting::updateOrCreate(
            [
                'school_id' => $schoolId,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
            ],
            [
                'amount_due' => round((float) $payload['amount_due'], 2),
                'set_by_user_id' => $request->user()->id,
            ]
        );

        $school = School::where('id', $schoolId)->first();
        if ($school) {
            $school->paystack_subaccount_code = trim((string) ($payload['paystack_subaccount_code'] ?? '')) ?: null;
            $school->save();
        }

        return response()->json([
            'message' => 'Payment settings saved.',
            'data' => [
                'id' => $setting->id,
                'amount_due' => (float) $setting->amount_due,
                'paystack_subaccount_code' => $school?->paystack_subaccount_code,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, (int) $request->query('per_page', 15));

        $q = SchoolFeePayment::query()
            ->where('school_fee_payments.school_id', $schoolId)
            ->where('school_fee_payments.academic_session_id', $session->id)
            ->where('school_fee_payments.term_id', $term->id)
            ->join('students', 'students.id', '=', 'school_fee_payments.student_id')
            ->join('users', 'users.id', '=', 'school_fee_payments.student_user_id')
            ->select([
                'school_fee_payments.id',
                'school_fee_payments.reference',
                'school_fee_payments.amount_paid',
                'school_fee_payments.currency',
                'school_fee_payments.status',
                'school_fee_payments.paid_at',
                'school_fee_payments.created_at',
                'students.id as student_id',
                'users.name as student_name',
                'users.email as student_email',
                'users.username as student_username',
            ])
            ->orderByDesc('school_fee_payments.id');

        if ($search !== '') {
            $q->where(function ($sub) use ($search) {
                $sub->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('school_fee_payments.reference', 'like', "%{$search}%");
            });
        }

        $p = $q->paginate($perPage);

        $items = collect($p->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'reference' => $row->reference,
                'amount_paid' => (float) $row->amount_paid,
                'currency' => $row->currency,
                'status' => $row->status,
                'paid_at' => $row->paid_at,
                'created_at' => $row->created_at,
                'student' => [
                    'id' => (int) $row->student_id,
                    'name' => $row->student_name,
                    'email' => $row->student_email,
                    'username' => $row->student_username,
                ],
            ];
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
            'context' => [
                'current_session' => [
                    'id' => $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                ],
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
}
