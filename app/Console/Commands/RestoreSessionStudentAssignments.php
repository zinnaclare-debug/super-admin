<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\ClassTemplateSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreSessionStudentAssignments extends Command
{
    protected $signature = 'students:restore-session-assignments
        {school : School subdomain or numeric ID}
        {--source= : Source academic session ID; latest session with assignments when omitted}
        {--target= : Target academic session ID; current session when omitted}
        {--apply : Write the previewed assignments and enrollments}';

    protected $description = 'Safely restore student assignments into an otherwise empty academic session';

    public function handle(): int
    {
        $school = $this->school((string) $this->argument('school'));
        $target = $school ? $this->targetSession($school) : null;
        $source = $school && $target ? $this->sourceSession($school, (int) $target->id) : null;

        if (!$school || !$target || !$source || (int) $source->id === (int) $target->id) {
            $this->error('A valid school, target session, and different source session are required.');
            return self::INVALID;
        }

        $counts = $this->targetCounts((int) $school->id, (int) $target->id);
        if ($counts['placements'] || $counts['enrollments']) {
            $this->error("Target session already has {$counts['placements']} placement(s) and {$counts['enrollments']} enrollment(s). No data was changed.");
            return self::FAILURE;
        }

        $plan = $this->plan($school, $source, $target);
        $this->render($school, $source, $target, $plan);

        if (!$this->option('apply')) {
            $this->comment('Preview only: no data was changed. Re-run with --apply only after reviewing this plan.');
            return self::SUCCESS;
        }

        if ($plan['unmapped'] || !$plan['moves']->count()) {
            $this->error('Recovery was not applied because one or more source classes have no safe target mapping.');
            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($school, $source, $target, $plan) {
            $fresh = $this->targetCounts((int) $school->id, (int) $target->id);
            if ($fresh['placements'] || $fresh['enrollments']) {
                throw new \RuntimeException('Target session changed after preview; recovery stopped.');
            }
            return $this->apply($school, $source, $target, $plan['moves']);
        });

        $this->info("Restored {$result['placements']} student placement(s) and {$result['enrollments']} term enrollment(s). Previous-session results were not changed.");
        return self::SUCCESS;
    }

    private function school(string $value): ?School
    {
        $value = trim($value);
        return ctype_digit($value)
            ? School::query()->find((int) $value)
            : School::query()->where('subdomain', $value)->first();
    }

    private function targetSession(School $school): ?AcademicSession
    {
        $id = trim((string) $this->option('target'));
        return $id !== ''
            ? AcademicSession::query()->where('school_id', $school->id)->find((int) $id)
            : AcademicSession::query()->where('school_id', $school->id)->where('status', 'current')->latest('id')->first();
    }

    private function sourceSession(School $school, int $targetId): ?AcademicSession
    {
        $id = trim((string) $this->option('source'));
        if ($id !== '') {
            return AcademicSession::query()->where('school_id', $school->id)->find((int) $id);
        }

        return AcademicSession::query()
            ->where('school_id', $school->id)
            ->where('id', '!=', $targetId)
            ->whereExists(function ($query) use ($school) {
                $query->selectRaw('1')->from('class_students')
                    ->whereColumn('class_students.academic_session_id', 'academic_sessions.id')
                    ->where('class_students.school_id', $school->id);
            })
            ->latest('id')
            ->first();
    }

    private function targetCounts(int $schoolId, int $sessionId): array
    {
        $placements = DB::table('class_students')->where('school_id', $schoolId)->where('academic_session_id', $sessionId)->count();
        $enrollments = DB::table('enrollments as e')
            ->join('terms as t', 't.id', '=', 'e.term_id')
            ->where('t.school_id', $schoolId)
            ->where('t.academic_session_id', $sessionId);
        if (Schema::hasColumn('enrollments', 'school_id')) $enrollments->where('e.school_id', $schoolId);

        return ['placements' => $placements, 'enrollments' => $enrollments->count()];
    }

    private function plan(School $school, AcademicSession $source, AcademicSession $target): array
    {
        $sourceClasses = SchoolClass::query()->where('school_id', $school->id)->where('academic_session_id', $source->id)->get(['id', 'name', 'level'])->keyBy('id');
        $targetQuery = SchoolClass::query()->where('school_id', $school->id)->where('academic_session_id', $target->id);
        if (Schema::hasColumn('classes', 'is_template_active')) $targetQuery->where('is_template_active', true);
        $targetClasses = $targetQuery->get(['id', 'name', 'level']);
        $sourceRows = DB::table('class_students')->where('school_id', $school->id)->where('academic_session_id', $source->id)->orderBy('id')->get(['student_id', 'class_id', 'roll_number', 'enrolled_at']);

        $moves = collect();
        $preview = [];
        $unmapped = 0;
        foreach ($sourceRows->groupBy('class_id') as $classId => $rows) {
            $sourceClass = $sourceClasses->get((int) $classId);
            $targetClass = $sourceClass ? $this->nextClass($school, $sourceClass, $targetClasses) : null;
            $status = $targetClass ? 'mapped' : 'not carried forward';
            if (!$targetClass && $sourceClass && $this->hasNextClass($school, $sourceClass)) {
                $status = 'unmapped';
                $unmapped += $rows->count();
            }
            if ($targetClass) {
                foreach ($rows as $row) $moves->push(['row' => $row, 'source' => $sourceClass, 'target' => $targetClass]);
            }
            $preview[] = [$sourceClass?->name ?? "Missing class #{$classId}", $targetClass?->name ?? '-', $rows->count(), $status];
        }

        return compact('moves', 'preview', 'unmapped');
    }

    private function nextClass(School $school, SchoolClass $source, Collection $targetClasses): ?SchoolClass
    {
        $sequence = $this->sequence($school);
        $index = collect($sequence)->search(fn ($item) => $item['level'] === strtolower(trim($source->level)) && $item['name'] === strtolower(trim($source->name)));
        if ($index === false || !isset($sequence[$index + 1])) return null;
        $next = $sequence[$index + 1];
        return $targetClasses->first(fn (SchoolClass $class) => strtolower(trim($class->level)) === $next['level'] && strtolower(trim($class->name)) === $next['name']);
    }

    private function hasNextClass(School $school, SchoolClass $source): bool
    {
        $sequence = $this->sequence($school);
        $index = collect($sequence)->search(fn ($item) => $item['level'] === strtolower(trim($source->level)) && $item['name'] === strtolower(trim($source->name)));
        return $index !== false && isset($sequence[$index + 1]);
    }

    private function sequence(School $school): array
    {
        $rows = [];
        foreach (ClassTemplateSchema::activeSections(ClassTemplateSchema::normalize($school->class_templates)) as $section) {
            $level = strtolower(trim((string) $section['key']));
            foreach (ClassTemplateSchema::activeClassNames($section) as $name) $rows[] = ['level' => $level, 'name' => strtolower(trim($name))];
        }
        return $rows;
    }

    private function apply(School $school, AcademicSession $source, AcademicSession $target, Collection $moves): array
    {
        $termIds = Term::query()->where('school_id', $school->id)->where('academic_session_id', $target->id)->pluck('id')->all();
        if (!$termIds) throw new \RuntimeException('Target session has no terms.');

        $sourceDepartments = $this->sourceDepartmentNames((int) $school->id, (int) $source->id);
        $targetDepartments = $this->targetDepartmentIds((int) $school->id, $moves);
        $now = now();
        $placements = 0;
        $enrollments = 0;
        $hasSchoolId = Schema::hasColumn('enrollments', 'school_id');
        foreach ($moves as $move) {
            $row = $move['row'];
            $class = $move['target'];
            $sourceKey = (int) $row->student_id . ':' . (int) $row->class_id;
            $departmentName = $sourceDepartments[$sourceKey] ?? null;
            $departmentId = $departmentName === null ? null : ($targetDepartments[(int) $class->id][strtolower($departmentName)] ?? null);

            DB::table('class_students')->updateOrInsert(
                ['class_id' => $class->id, 'student_id' => $row->student_id],
                ['school_id' => $school->id, 'academic_session_id' => $target->id, 'roll_number' => $row->roll_number, 'enrolled_at' => $row->enrolled_at ?: $now->toDateString(), 'created_at' => $now, 'updated_at' => $now]
            );
            DB::table('students')->where('school_id', $school->id)->where('id', $row->student_id)->update(['education_level' => $class->level, 'updated_at' => $now]);
            $placements++;
            foreach ($termIds as $termId) {
                $where = ['student_id' => $row->student_id, 'class_id' => $class->id, 'term_id' => $termId];
                $values = ['department_id' => $departmentId, 'created_at' => $now, 'updated_at' => $now];
                if ($hasSchoolId) { $where['school_id'] = $school->id; $values['school_id'] = $school->id; }
                DB::table('enrollments')->updateOrInsert($where, $values);
                $enrollments++;
            }
        }
        return compact('placements', 'enrollments');
    }

    private function sourceDepartmentNames(int $schoolId, int $sessionId): array
    {
        if (!Schema::hasTable('class_departments')) return [];

        $query = DB::table('enrollments as e')
            ->join('terms as t', 't.id', '=', 'e.term_id')
            ->leftJoin('class_departments as d', 'd.id', '=', 'e.department_id')
            ->where('t.school_id', $schoolId)
            ->where('t.academic_session_id', $sessionId)
            ->whereNotNull('e.department_id')
            ->orderByDesc('e.id');
        if (Schema::hasColumn('enrollments', 'school_id')) $query->where('e.school_id', $schoolId);

        $names = [];
        foreach ($query->get(['e.student_id', 'e.class_id', 'd.name']) as $row) {
            $key = (int) $row->student_id . ':' . (int) $row->class_id;
            if (!isset($names[$key]) && trim((string) $row->name) !== '') $names[$key] = trim((string) $row->name);
        }
        return $names;
    }

    private function targetDepartmentIds(int $schoolId, Collection $moves): array
    {
        if (!Schema::hasTable('class_departments')) return [];

        $classIds = $moves->map(fn ($move) => (int) $move['target']->id)->unique()->values()->all();
        if (!$classIds) return [];

        $ids = [];
        foreach (DB::table('class_departments')->where('school_id', $schoolId)->whereIn('class_id', $classIds)->get(['id', 'class_id', 'name']) as $row) {
            $ids[(int) $row->class_id][strtolower(trim((string) $row->name))] = (int) $row->id;
        }
        return $ids;
    }
    private function render(School $school, AcademicSession $source, AcademicSession $target, array $plan): void
    {
        $this->info("Recovery preview: {$school->name} | {$source->session_name} -> {$target->session_name}");
        $this->table(['Source class', 'Target class', 'Students', 'Status'], $plan['preview']);
        $this->line("Students to restore: {$plan['moves']->count()} | Unsafe/unmapped students: {$plan['unmapped']}");
    }
}