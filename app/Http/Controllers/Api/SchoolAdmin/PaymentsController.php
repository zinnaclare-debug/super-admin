<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolFeePayment;
use App\Models\SchoolFeeSetting;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $activeLevels = $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []);
        $settings = SchoolFeeSetting::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->get();
        $settingsByLevel = $settings
            ->filter(fn ($row) => !empty($row->level))
            ->keyBy(fn ($row) => strtolower(trim((string) $row->level)));
        $feesByLevel = [];
        foreach ($activeLevels as $level) {
            $feesByLevel[$level] = isset($settingsByLevel[$level])
                ? (float) $settingsByLevel[$level]->amount_due
                : null;
        }
        $legacySetting = $settings->first(fn ($row) => empty($row->level));
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
                'active_levels' => $activeLevels,
                'fees_by_level' => $feesByLevel,
                'legacy_amount_due' => $legacySetting ? (float) $legacySetting->amount_due : null,
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
            'fees_by_level' => 'sometimes|array',
            'fees_by_level.*' => 'nullable|numeric|min:0',
            'amount_due' => 'sometimes|numeric|min:0',
            'paystack_subaccount_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        $activeLevels = $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []);
        $allowedLevelSet = collect($activeLevels)->flip();

        $normalizedFees = [];
        if (array_key_exists('fees_by_level', $payload)) {
            foreach ((array) $payload['fees_by_level'] as $level => $amount) {
                $normalizedLevel = strtolower(trim((string) $level));
                if (!$allowedLevelSet->has($normalizedLevel)) {
                    return response()->json([
                        'message' => "Invalid level '{$level}' for this session.",
                    ], 422);
                }
                if ($amount === null || $amount === '') {
                    continue;
                }
                $normalizedFees[$normalizedLevel] = round((float) $amount, 2);
            }
        }

        DB::transaction(function () use (
            $schoolId,
            $session,
            $term,
            $request,
            $normalizedFees,
            $payload
        ) {
            SchoolFeeSetting::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->delete();

            if (!empty($normalizedFees)) {
                foreach ($normalizedFees as $level => $amountDue) {
                    SchoolFeeSetting::create([
                        'school_id' => $schoolId,
                        'academic_session_id' => $session->id,
                        'term_id' => $term->id,
                        'level' => $level,
                        'amount_due' => $amountDue,
                        'set_by_user_id' => $request->user()->id,
                    ]);
                }
            } elseif (array_key_exists('amount_due', $payload)) {
                // Legacy fallback for old clients still sending a single amount.
                SchoolFeeSetting::create([
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'level' => null,
                    'amount_due' => round((float) $payload['amount_due'], 2),
                    'set_by_user_id' => $request->user()->id,
                ]);
            }
        });

        $school = School::where('id', $schoolId)->first();
        if ($school) {
            $school->paystack_subaccount_code = trim((string) ($payload['paystack_subaccount_code'] ?? '')) ?: null;
            $school->save();
        }

        $saved = SchoolFeeSetting::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->get();
        $savedByLevel = $saved
            ->filter(fn ($row) => !empty($row->level))
            ->keyBy(fn ($row) => strtolower(trim((string) $row->level)));
        $feesByLevel = [];
        foreach ($activeLevels as $level) {
            $feesByLevel[$level] = isset($savedByLevel[$level])
                ? (float) $savedByLevel[$level]->amount_due
                : null;
        }

        return response()->json([
            'message' => 'Payment settings saved.',
            'data' => [
                'active_levels' => $activeLevels,
                'fees_by_level' => $feesByLevel,
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
                'active_levels' => $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []),
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

    private function extractSessionLevels(int $schoolId, int $sessionId, mixed $rawLevels): array
    {
        $allowed = ['nursery', 'primary', 'secondary'];

        $levels = collect(is_array($rawLevels) ? $rawLevels : [])
            ->map(function ($item) {
                $name = is_array($item) ? ($item['level'] ?? null) : $item;
                return strtolower(trim((string) $name));
            })
            ->filter()
            ->values()
            ->all();

        $levels = array_values(array_unique(array_intersect($levels, $allowed)));
        if (!empty($levels)) {
            return $levels;
        }

        $classLevels = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->pluck('level')
            ->map(fn ($level) => strtolower(trim((string) $level)))
            ->filter(fn ($level) => in_array($level, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return !empty($classLevels) ? $classLevels : ['primary', 'secondary'];
    }
}
