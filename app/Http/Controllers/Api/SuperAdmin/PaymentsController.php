<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeePayment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function schools()
    {
        $schools = School::orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return response()->json(['data' => $schools]);
    }

    public function index(Request $request)
    {
        $payload = $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $schoolId = (int) $payload['school_id'];
        $search = trim((string) ($payload['search'] ?? ''));
        $perPage = (int) ($payload['per_page'] ?? 15);

        $q = SchoolFeePayment::query()
            ->where('school_fee_payments.school_id', $schoolId)
            ->join('students', 'students.id', '=', 'school_fee_payments.student_id')
            ->join('users', 'users.id', '=', 'school_fee_payments.student_user_id')
            ->join('academic_sessions', 'academic_sessions.id', '=', 'school_fee_payments.academic_session_id')
            ->join('terms', 'terms.id', '=', 'school_fee_payments.term_id')
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
                'academic_sessions.session_name as session_name',
                'academic_sessions.academic_year as academic_year',
                'terms.name as term_name',
            ])
            ->orderByDesc('school_fee_payments.id');

        if ($search !== '') {
            $q->where(function ($sub) use ($search) {
                $sub->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('school_fee_payments.reference', 'like', "%{$search}%")
                    ->orWhere('terms.name', 'like', "%{$search}%")
                    ->orWhere('academic_sessions.session_name', 'like', "%{$search}%");
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
                'session_name' => $row->session_name,
                'academic_year' => $row->academic_year,
                'term_name' => $row->term_name,
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
        ]);
    }
}
