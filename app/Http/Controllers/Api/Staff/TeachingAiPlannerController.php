<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\CompressTeachingMaterialJob;
use App\Jobs\GenerateTeachingAiPackJob;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\TeachingAiGenerationJob;
use App\Models\TeachingMaterial;
use App\Models\Term;
use App\Models\TermSubject;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class TeachingAiPlannerController extends Controller
{
    private const DOCUMENTS = [
        'exam_questions' => ['path' => 'exam_questions_path', 'category' => TeachingMaterial::CATEGORY_EXAM_QUESTION, 'label' => 'Exam Questions'],
        'lesson_notes' => ['path' => 'lesson_notes_path', 'category' => TeachingMaterial::CATEGORY_LESSON_NOTE, 'label' => 'Lesson Notes'],
        'lesson_plan' => ['path' => 'lesson_plan_path', 'category' => TeachingMaterial::CATEGORY_LESSON_PLAN, 'label' => 'Lesson Plan'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        [$session, $term] = $this->currentCycle((int) $user->school_id);
        if (!$session || !$term) {
            return response()->json(['data' => []]);
        }

        $jobs = TeachingAiGenerationJob::query()
            ->where('school_id', $user->school_id)
            ->where('staff_user_id', $user->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (TeachingAiGenerationJob $job) => $this->payload($job));

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        [$session, $term] = $this->currentCycle((int) $user->school_id);
        if (!$session || !$term) {
            return response()->json(['message' => 'No current academic session/term configured.'], 422);
        }

        $data = $request->validate([
            'term_subject_id' => ['required', 'integer'],
            'topics' => ['required', 'string', 'min:20', 'max:1200'],
            'question_count' => ['nullable', 'integer', 'min:5', 'max:5'],
            'document_type' => ['required', Rule::in(array_keys(self::DOCUMENTS))],
        ]);

        $termSubject = $this->assignedSubject((int) $user->school_id, (int) $user->id, (int) $session->id, (int) $term->id, (int) $data['term_subject_id']);
        if (!$termSubject) {
            return response()->json(['message' => 'Select a subject assigned to you for the current term.'], 422);
        }

        $alreadyRunning = TeachingAiGenerationJob::query()
            ->where('school_id', $user->school_id)
            ->where('staff_user_id', $user->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('term_subject_id', $termSubject->id)
            ->where('result_json->_document_type', (string) $data['document_type'])
            ->whereIn('status', [TeachingAiGenerationJob::STATUS_QUEUED, TeachingAiGenerationJob::STATUS_PROCESSING])
            ->exists();
        if ($alreadyRunning) {
            return response()->json(['message' => 'A teaching pack is already being generated for this subject.'], 422);
        }

        $job = TeachingAiGenerationJob::create([
            'school_id' => $user->school_id,
            'staff_user_id' => $user->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'term_subject_id' => $termSubject->id,
            'subject_id' => $termSubject->subject_id,
            'topics' => trim($data['topics']),
            'question_count' => (int) ($data['question_count'] ?? 5),
            'result_json' => ['_document_type' => (string) $data['document_type']],
            'status' => TeachingAiGenerationJob::STATUS_QUEUED,
            'progress' => 0,
        ]);

        GenerateTeachingAiPackJob::dispatch((int) $job->id);

        return response()->json([
            'message' => (self::DOCUMENTS[(string) $data['document_type']]['label'] ?? 'Teaching document') . ' generation queued.',
            'data' => $this->payload($job),
        ], 201);
    }

    public function show(Request $request, TeachingAiGenerationJob $generationJob)
    {
        $this->guardJob($request, $generationJob);
        return response()->json(['data' => $this->payload($generationJob->fresh())]);
    }

    public function download(Request $request, TeachingAiGenerationJob $generationJob, string $document)
    {
        $this->guardJob($request, $generationJob);
        $definition = self::DOCUMENTS[$document] ?? null;
        abort_unless($definition, 404);
        $path = $generationJob->{$definition['path']};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path, $this->fileName($generationJob, $document));
    }

    public function clear(Request $request, TeachingAiGenerationJob $generationJob, string $document)
    {
        $this->guardJob($request, $generationJob);
        $definition = self::DOCUMENTS[$document] ?? null;
        abort_unless($definition, 404);

        $path = $generationJob->{$definition['path']};
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        $generationJob->forceFill([$definition['path'] => null])->save();

        return response()->json(['message' => $definition['label'] . ' draft cleared.', 'data' => $this->payload($generationJob->fresh())]);
    }

    public function saveToTeaching(Request $request, TeachingAiGenerationJob $generationJob, string $document)
    {
        $user = $request->user();
        $this->guardJob($request, $generationJob);
        $definition = self::DOCUMENTS[$document] ?? null;
        abort_unless($definition, 404);

        $sourcePath = $generationJob->{$definition['path']};
        if (!$sourcePath || !Storage::disk('public')->exists($sourcePath)) {
            return response()->json(['message' => 'Generate this document again before saving it to current term uploads.'], 422);
        }

        if ($definition['category'] === TeachingMaterial::CATEGORY_EXAM_QUESTION) {
            $old = TeachingMaterial::query()
                ->where('school_id', $generationJob->school_id)
                ->where('staff_user_id', $generationJob->staff_user_id)
                ->where('academic_session_id', $generationJob->academic_session_id)
                ->where('term_id', $generationJob->term_id)
                ->where('term_subject_id', $generationJob->term_subject_id)
                ->where('category', TeachingMaterial::CATEGORY_EXAM_QUESTION)
                ->get();
            foreach ($old as $material) {
                Storage::disk('public')->delete($material->file_path);
                $material->delete();
            }
        }

        $directory = "schools/{$generationJob->school_id}/teaching/{$generationJob->academic_session_id}/{$generationJob->term_id}/{$user->id}/{$generationJob->term_subject_id}";
        $target = $directory . '/' . $this->fileName($generationJob, $document);
        Storage::disk('public')->copy($sourcePath, $target);
        $size = Storage::disk('public')->size($target);

        $material = TeachingMaterial::create([
            'school_id' => $generationJob->school_id,
            'staff_user_id' => $user->id,
            'academic_session_id' => $generationJob->academic_session_id,
            'term_id' => $generationJob->term_id,
            'term_subject_id' => $generationJob->term_subject_id,
            'subject_id' => $generationJob->subject_id,
            'category' => $definition['category'],
            'title' => 'AI Generated ' . $definition['label'],
            'original_name' => $this->fileName($generationJob, $document),
            'mime_type' => 'application/pdf',
            'file_path' => $target,
            'file_size' => $size,
            'status' => TeachingMaterial::STATUS_PROCESSING,
            'processing_note' => 'AI draft saved and queued for compression.',
        ]);
        CompressTeachingMaterialJob::dispatch((int) $material->id);

        return response()->json(['message' => $definition['label'] . ' saved to Current Term Uploads and queued for compression.']);
    }

    public function generateForQueue(TeachingAiGenerationJob $job): void
    {
        $subject = $this->assignedSubject($job->school_id, $job->staff_user_id, $job->academic_session_id, $job->term_id, $job->term_subject_id);
        if (!$subject) {
            throw new RuntimeException('The subject assignment is no longer available.');
        }

        $document = $this->documentType($job);
        $job->forceFill(['progress' => 25])->save();
        $content = $document === 'exam_questions'
            ? $this->generateExamQuestions($job, $subject)
            : $this->askAi($job, $subject, $document);
        $this->validateContent($content, $job->question_count, $document);

        $job->forceFill(['progress' => 70, 'result_json' => array_merge(['_document_type' => $document], $content)])->save();
        $school = School::query()->findOrFail($job->school_id);
        $directory = "schools/{$job->school_id}/teaching-ai/{$job->academic_session_id}/{$job->term_id}/{$job->staff_user_id}/{$job->id}";

        foreach ([$document] as $document) {
            $html = view('pdf.teaching_ai_pack', [
                'school' => $school,
                'job' => $job,
                'subject' => $subject,
                'document' => $document,
                'content' => $content,
            ])->render();
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->render();
            $path = $directory . '/' . $this->fileName($job, $document);
            Storage::disk('public')->put($path, $pdf->output());
            $job->{self::DOCUMENTS[$document]['path']} = $path;
            $job->progress = min(95, $job->progress + 8);
            $job->save();
        }

        $job->forceFill([
            'status' => TeachingAiGenerationJob::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ])->save();
    }

    private function generateExamQuestions(TeachingAiGenerationJob $job, object $subject): array
    {
        $questions = [];
        $seen = [];

        for ($number = 1; $number <= $job->question_count; $number++) {
            $job->forceFill(['progress' => 25 + ($number * 7)])->save();
            $question = $this->askAi($job, $subject, 'exam_questions', $number, $questions);
            $text = trim((string) ($question['question'] ?? ''));
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $text) ?? '');

            if ($key === '' || isset($seen[$key])) {
                throw new RuntimeException('AI returned a duplicate or empty exam question.');
            }

            $seen[$key] = true;
            $questions[] = [
                'question' => $text,
                'marks' => max(1, min(100, (int) ($question['marks'] ?? 5))),
                'answer_guide' => trim((string) ($question['answer_guide'] ?? '')),
            ];
        }

        return ['exam_questions' => $questions];
    }

    private function askAi(TeachingAiGenerationJob $job, object $subject, string $document, ?int $questionNumber = null, array $previousQuestions = []): array
    {
        $baseUrl = rtrim((string) config('services.ai.base_url', 'https://api.openai.com/v1'), '/');
        $apiKey = trim((string) config('services.ai.api_key', ''));
        $isLocalEndpoint = (bool) preg_match('/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?(\/|$)/i', $baseUrl);
        if ($apiKey === '' && !$isLocalEndpoint) {
            throw new RuntimeException('AI service is not configured.');
        }

        $isSingleQuestion = $document === 'exam_questions' && $questionNumber !== null;
        $shape = $isSingleQuestion
            ? '{"question":"...","marks":5,"answer_guide":"..."}'
            : match ($document) {
                'lesson_notes' => '{"lesson_notes":[{"topic":"...","objectives":["..."],"content":"...","activities":"...","assessment":"...","homework":"...","references":"..."}]}',
                default => '{"lesson_plan":[{"week":"Week 1","topic":"...","duration":"40 minutes","objectives":["..."],"resources":["..."],"introduction":"...","teacher_activities":"...","learner_activities":"...","assessment":"...","conclusion":"..."}]}',
            };
        $previous = collect($previousQuestions)->pluck('question')->implode(' | ');
        $instruction = $isSingleQuestion
            ? "Write one original hard application or analysis exam question number {$questionNumber}. Keep the question and answer guide to one short sentence. Do not repeat these earlier questions: {$previous}."
            : match ($document) {
                'lesson_notes' => 'Write exactly one concise lesson note. Each text value must be one short sentence. Objectives maximum two.',
                default => 'Write exactly one concise lesson plan. Each text value must be one short sentence. Objectives and resources maximum two.',
            };
        $system = "Return valid JSON only. Shape: {$shape}. {$instruction} Nigerian curriculum context where relevant. No markdown.";
        $topicText = mb_substr(trim((string) $job->topics), 0, 1200);
        $prompt = "Subject: {$subject->subject_name}; Class: {$subject->class_name}; Level: {$subject->class_level}; Topics: {$topicText}";
        $maxOutput = match ($document) {
            'exam_questions' => 110,
            'lesson_notes' => 220,
            default => 240,
        };
        $http = Http::timeout(max(45, min((int) config('services.ai.timeout', 150), 150)))
            ->connectTimeout(max(5, min((int) config('services.ai.connect_timeout', 30), 30)))
            ->acceptJson();

        if ($isLocalEndpoint) {
            $nativeBaseUrl = preg_replace('#/v1$#', '', $baseUrl) ?: $baseUrl;
            $response = $http->post($nativeBaseUrl . '/api/generate', [
                'model' => config('services.ai.model', 'phi3.5:latest'),
                'prompt' => $system . "\n\n" . $prompt,
                'stream' => false,
                'keep_alive' => '10m',
                'options' => ['temperature' => 0.2, 'num_ctx' => 1024, 'num_predict' => $maxOutput],
            ]);
            $raw = trim((string) data_get($response->json(), 'response', ''));
        } else {
            if ($apiKey !== '') $http = $http->withToken($apiKey);
            $response = $http->post($baseUrl . '/chat/completions', [
                'model' => config('services.ai.model', 'gpt-4.1-mini'),
                'temperature' => 0.25,
                'max_tokens' => $maxOutput,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
            ]);
            $raw = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        }
        if ($response->failed()) throw new RuntimeException('AI provider did not complete the request.');
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?: '';
        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            $raw = substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new RuntimeException('AI returned an invalid document format.');
        return $decoded;
    }

    private function validateContent(array $content, int $questionCount, string $document): void
    {
        if ($document === 'exam_questions') {
            $questions = array_values(array_filter($content['exam_questions'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['question'] ?? '')) !== ''));
            if (count($questions) !== $questionCount) throw new RuntimeException('AI returned an incomplete exam-question document.');
            $seen = [];
            foreach ($questions as $question) {
                $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $question['question']) ?? '');
                if ($key === '' || isset($seen[$key])) throw new RuntimeException('AI returned duplicate questions.');
                $seen[$key] = true;
            }
            return;
        }

        $items = $content[$document] ?? [];
        if (!is_array($items) || empty($items)) throw new RuntimeException('AI returned an incomplete teaching document.');
    }

    private function documentType(TeachingAiGenerationJob $job): string
    {
        $type = (string) data_get($job->result_json, '_document_type', 'exam_questions');
        return array_key_exists($type, self::DOCUMENTS) ? $type : 'exam_questions';
    }
    private function currentCycle(int $schoolId): array
    {
        $session = AcademicSession::query()->where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) return [null, null];
        $term = Term::query()->where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
            ?: Term::query()->where('school_id', $schoolId)->where('academic_session_id', $session->id)->orderBy('id')->first();
        return [$session, $term];
    }

    private function assignedSubject(int $schoolId, int $staffId, int $sessionId, int $termId, int $termSubjectId): ?object
    {
        return TermSubject::query()->where('term_subjects.school_id', $schoolId)->where('term_subjects.teacher_user_id', $staffId)->where('term_subjects.id', $termSubjectId)->where('term_subjects.term_id', $termId)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->where('classes.academic_session_id', $sessionId)
            ->first(['term_subjects.id', 'term_subjects.subject_id', 'subjects.name as subject_name', 'classes.name as class_name', 'classes.level as class_level']);
    }

    private function guardJob(Request $request, TeachingAiGenerationJob $job): void
    {
        abort_unless((int) $job->school_id === (int) $request->user()->school_id && (int) $job->staff_user_id === (int) $request->user()->id, 404);
    }

    private function payload(TeachingAiGenerationJob $job): array
    {
        $document = $this->documentType($job);
        $definition = self::DOCUMENTS[$document];
        $ready = (bool) ($job->{$definition['path']} && Storage::disk('public')->exists($job->{$definition['path']}));
        $documents = [$document => ['label' => $definition['label'], 'ready' => $ready]];
        return ['id' => $job->id, 'term_subject_id' => $job->term_subject_id, 'document_type' => $document, 'status' => $job->status, 'progress' => $job->progress, 'topics' => $job->topics, 'question_count' => $job->question_count, 'documents' => $documents, 'error_message' => $job->error_message, 'created_at' => optional($job->created_at)?->toDateTimeString()];
    }

    private function fileName(TeachingAiGenerationJob $job, string $document): string
    {
        $label = strtolower(str_replace(' ', '_', self::DOCUMENTS[$document]['label']));
        return "ai_{$label}_{$job->id}.pdf";
    }
}
