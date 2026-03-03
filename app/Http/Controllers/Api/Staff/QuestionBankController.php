<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\QuestionBankQuestion;
use App\Models\Subject;
use App\Models\TermSubject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class QuestionBankController extends Controller
{
  private function questionKey(array $q): string
  {
    $parts = [
      strtolower(trim((string)($q['question_text'] ?? ''))),
      strtolower(trim((string)($q['option_a'] ?? ''))),
      strtolower(trim((string)($q['option_b'] ?? ''))),
      strtolower(trim((string)($q['option_c'] ?? ''))),
      strtolower(trim((string)($q['option_d'] ?? ''))),
    ];

    $normalized = implode('|', array_map(
      fn($v) => preg_replace('/\s+/', ' ', (string)$v) ?? '',
      $parts
    ));

    return trim((string)$normalized);
  }

  private function uniqueQuestions(array $rows): array
  {
    $seen = [];
    $unique = [];
    foreach ($rows as $q) {
      $row = (array)$q;
      $key = $this->questionKey($row);
      if ($key === '' || isset($seen[$key])) {
        continue;
      }
      $seen[$key] = true;
      $unique[] = $row;
    }
    return $unique;
  }

  private function withUniqueFallbackFill(array $rows, string $subjectName, string $prompt, int $count): array
  {
    $unique = $this->uniqueQuestions($rows);
    if (count($unique) >= $count) {
      return array_slice($unique, 0, $count);
    }

    $needed = $count - count($unique);
    $pool = $this->buildFallbackQuestions($subjectName, $prompt, max($count * 2, $needed + 2));
    $merged = $this->uniqueQuestions(array_merge($unique, $pool));

    return array_slice($merged, 0, $count);
  }

  private function extractPromptTopic(string $prompt, string $subjectName): string
  {
    $clean = trim((string) (preg_replace('/\s+/', ' ', strip_tags($prompt)) ?? ''));
    $clean = preg_replace('/^(what\s+is|define|explain|describe|discuss|write\s+about)\s+/i', '', $clean) ?? $clean;
    $clean = preg_replace('/[?.!]+$/', '', $clean) ?? $clean;

    $words = preg_split('/\s+/', $clean) ?: [];
    $topic = trim(implode(' ', array_slice($words, 0, 8)));

    if ($topic !== '') {
      return $topic;
    }

    return trim($subjectName) !== '' ? trim($subjectName) : 'this topic';
  }

  private function buildFallbackMcq(
    string $questionText,
    string $correctOptionText,
    array $wrongPool,
    string $explanation,
    int $index
  ): array {
    $correct = trim($correctOptionText);
    if ($correct === '') {
      $correct = 'Correct option';
    }

    $wrong = [];
    $wrongSeen = [];
    foreach ($wrongPool as $item) {
      $candidate = trim((string)$item);
      $key = strtolower($candidate);
      if ($candidate === '' || strcasecmp($candidate, $correct) === 0 || isset($wrongSeen[$key])) {
        continue;
      }
      $wrongSeen[$key] = true;
      $wrong[] = $candidate;
      if (count($wrong) >= 3) {
        break;
      }
    }

    $fallbackWrong = [
      'It is based on guessing without understanding.',
      'It avoids practice and ignores feedback.',
      'It relies only on unrelated facts.',
    ];
    foreach ($fallbackWrong as $item) {
      if (count($wrong) >= 3) {
        break;
      }
      if (strcasecmp($item, $correct) !== 0 && !in_array(strtolower($item), array_map('strtolower', $wrong), true)) {
        $wrong[] = $item;
      }
    }

    $options = array_merge([$correct], array_slice($wrong, 0, 3));
    while (count($options) < 4) {
      $options[] = 'None of the above';
    }

    $shift = $index % 4;
    $rotated = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
    $letters = ['A', 'B', 'C', 'D'];
    $correctIndex = 0;
    foreach ($rotated as $i => $opt) {
      if ($opt === $correct) {
        $correctIndex = $i;
        break;
      }
    }

    return [
      'question_text' => trim($questionText),
      'option_a' => $rotated[0],
      'option_b' => $rotated[1],
      'option_c' => $rotated[2],
      'option_d' => $rotated[3],
      'correct_option' => $letters[$correctIndex] ?? 'A',
      'explanation' => trim($explanation),
      'source_type' => 'ai',
    ];
  }

  private function buildFallbackQuestions(string $subjectName, string $prompt, int $count): array
  {
    $cleanPrompt = trim((string)(preg_replace('/\s+/', ' ', $prompt) ?? ''));
    $subjectLabel = trim($subjectName) !== '' ? $subjectName : 'the subject';
    $topic = $this->extractPromptTopic($cleanPrompt, $subjectLabel);
    $topicTitle = ucfirst($topic);

    preg_match_all('/[A-Za-z][A-Za-z0-9\-]{2,}/', strtolower($cleanPrompt), $matches);
    $keywords = array_values(array_unique(array_slice($matches[0] ?? [], 0, 10)));
    if (empty($keywords)) {
      $keywords = ['concept', 'practice', 'application', 'understanding'];
    }

    $k1 = ucfirst($keywords[0] ?? 'Concept');
    $k2 = ucfirst($keywords[1] ?? 'Practice');
    $k3 = ucfirst($keywords[2] ?? 'Application');
    $k4 = ucfirst($keywords[3] ?? 'Understanding');

    $rows = [];
    $rows[] = $this->buildFallbackMcq(
      "Which statement best defines {$topicTitle}?",
      "{$topicTitle} means understanding core ideas and applying them correctly.",
      [
        "{$topicTitle} means memorizing terms without understanding.",
        "{$topicTitle} means avoiding examples and real tasks.",
        "{$topicTitle} means using unrelated facts only.",
      ],
      "A good definition includes understanding and correct application.",
      0
    );

    $rows[] = $this->buildFallbackMcq(
      "Which activity is the best practical example of {$topicTitle}?",
      "Applying {$topicTitle} concepts to solve a real classroom task.",
      [
        "Copying answers without checking how they were obtained.",
        "Skipping practice and waiting for final exams.",
        "Studying topics that are unrelated to {$topicTitle}.",
      ],
      "Practical examples involve real application, not passive memorization.",
      1
    );

    $rows[] = $this->buildFallbackMcq(
      "Which study habit most improves performance in {$topicTitle}?",
      "Regular practice, correction of mistakes, and feedback review.",
      [
        "Relying only on last-minute cramming.",
        "Ignoring feedback after each attempt.",
        "Studying without solving any questions.",
      ],
      "Consistent practice and feedback are the strongest improvement tools.",
      2
    );

    $rows[] = $this->buildFallbackMcq(
      "Which option is NOT a good approach to learning {$topicTitle}?",
      "Guessing answers without understanding the underlying idea.",
      [
        "Reviewing worked examples and explanations.",
        "Linking new ideas to what was learned before.",
        "Practicing with varied question formats.",
      ],
      "Guessing without understanding prevents long-term mastery.",
      3
    );

    $rows[] = $this->buildFallbackMcq(
      "In {$subjectLabel}, what should a student do first when learning {$topicTitle}?",
      "Understand the key concept, then apply it with guided examples.",
      [
        "Jump straight to difficult tests without learning basics.",
        "Memorize answers only and skip concept review.",
        "Avoid asking questions when confused.",
      ],
      "Strong foundations come before advanced problem-solving.",
      4
    );

    $rows[] = $this->buildFallbackMcq(
      "Which pair best matches {$topicTitle}?",
      "{$k1} and {$k3} used together in problem-solving.",
      [
        "{$k2} and random guessing without checking.",
        "{$k4} and ignoring correction feedback.",
        "{$k1} and unrelated assumptions only.",
      ],
      "The best pair combines concept and application.",
      5
    );

    $rows = $this->uniqueQuestions($rows);
    for ($i = count($rows); $i < $count; $i++) {
      $rows[] = $this->buildFallbackMcq(
        "Which action best strengthens {$topicTitle} skills in {$subjectLabel}? (#" . ($i + 1) . ")",
        "Practice on mixed questions and review each explanation carefully.",
        [
          "Read only the topic title and skip the lesson details.",
          "Use one method for every question without checking fit.",
          "Ignore errors because final answers matter more than process.",
        ],
        "Skill growth requires practice, reflection, and correction.",
        $i
      );
      $rows = $this->uniqueQuestions($rows);
    }

    return array_slice($rows, 0, $count);
  }

  private function maybeImportToBank(array $generated, int $schoolId, int $teacherUserId, int $subjectId): void
  {
    foreach ($generated as $q) {
      QuestionBankQuestion::create([
        'school_id' => $schoolId,
        'teacher_user_id' => $teacherUserId,
        'subject_id' => $subjectId,
        ...$q,
        'media_path' => null,
        'media_type' => null,
      ]);
    }
  }

  private function aiErrorMeta($response): array
  {
    $providerStatus = (int) $response->status();
    $errorCode = data_get($response->json(), 'error.code');
    $providerMessage = trim((string) (
      data_get($response->json(), 'error.message')
      ?: data_get($response->json(), 'message')
      ?: ''
    ));

    return [$providerStatus, $errorCode, $providerMessage];
  }

  private function localModelFallbackCandidates(string $model): array
  {
    $fallback = trim((string) config('services.ai.fallback_model', ''));
    $base = preg_replace('/:.+$/', '', $model);

    $candidates = array_values(array_filter([
      $fallback,
      $base,
      $base !== '' ? "{$base}:latest" : null,
    ], fn($v) => is_string($v) && trim($v) !== ''));

    $seen = [];
    $unique = [];
    foreach ($candidates as $candidate) {
      $key = strtolower(trim($candidate));
      if ($key === '' || isset($seen[$key])) {
        continue;
      }
      $seen[$key] = true;
      $unique[] = trim($candidate);
    }

    return $unique;
  }

  private function stripQuestionPrefix(string $text): string
  {
    $cleaned = preg_replace('/^\s*(?:\(?\d+\)?[\).\-\:]*|[ivxlcdm]+[\).\-\:])\s*/i', '', $text);
    return trim((string)$cleaned);
  }

  private function extractJsonObject(string $content): ?array
  {
    $trimmed = trim($content);

    if (str_starts_with($trimmed, '```')) {
      $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
      $trimmed = preg_replace('/\s*```$/', '', (string)$trimmed);
      $trimmed = trim((string)$trimmed);
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      return $decoded;
    }

    if (preg_match('/\{.*\}/s', $content, $m)) {
      $decoded2 = json_decode($m[0], true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) {
        return $decoded2;
      }
    }

    return null;
  }

  private function hasTeacherAssignmentColumn(): bool
  {
    return Schema::hasColumn('term_subjects', 'teacher_user_id');
  }

  // GET /api/staff/question-bank/subjects?session_id=&term_id=
  // For filter UX only. Question bank itself is not session-bound.
  public function subjects(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;
    $data = $request->validate([
      'session_id' => 'nullable|integer',
      'term_id' => 'nullable|integer',
    ]);

    if (!$this->hasTeacherAssignmentColumn()) {
      return response()->json([
        'data' => [],
        'message' => 'Teacher assignment schema is missing. Run database migrations and try again.'
      ]);
    }

    $session = null;
    if (!empty($data['session_id'])) {
      $session = AcademicSession::where('id', $data['session_id'])
        ->where('school_id', $schoolId)
        ->first();
    }
    if (!$session) {
      $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    }

    if (!$session) return response()->json(['data' => []]);

    $query = TermSubject::query()
      ->where('term_subjects.school_id', $schoolId)
      ->where('term_subjects.teacher_user_id', $user->id)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->where('terms.academic_session_id', $session->id)
      ->where('classes.academic_session_id', $session->id);

    if (!empty($data['term_id'])) {
      $query->where('term_subjects.term_id', (int)$data['term_id']);
    }

    $rows = $query
      ->orderBy('classes.level')
      ->orderBy('classes.name')
      ->orderBy('terms.id')
      ->orderBy('subjects.name')
      ->get([
        'term_subjects.id as term_subject_id',
        'term_subjects.subject_id as subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.id as term_id',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $rows]);
  }

  // GET /api/staff/question-bank?subject_id=&term_subject_id=
  // not session-guided: shows all questions teacher created in school
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    $data = $request->validate([
      'subject_id' => 'nullable|integer',
      'term_subject_id' => 'nullable|integer',
    ]);

    $query = QuestionBankQuestion::query()
      ->leftJoin('subjects', 'subjects.id', '=', 'question_bank_questions.subject_id')
      ->where('question_bank_questions.school_id', $schoolId)
      ->where('question_bank_questions.teacher_user_id', $user->id);

    if (!empty($data['subject_id'])) {
      $query->where('question_bank_questions.subject_id', (int)$data['subject_id']);
    }

    if (!empty($data['term_subject_id'])) {
      $subjectId = TermSubject::where('school_id', $schoolId)
        ->where('id', (int)$data['term_subject_id'])
        ->value('subject_id');

      if ($subjectId) {
        $query->where('question_bank_questions.subject_id', (int)$subjectId);
      }
    }

    $items = $query
      ->orderByDesc('question_bank_questions.id')
      ->get([
        'question_bank_questions.*',
        'subjects.name as subject_name',
      ])
      ->map(function ($q) {
        $q->media_url = $q->media_path ? Storage::disk('public')->url($q->media_path) : null;
        return $q;
      });

    return response()->json(['data' => $items]);
  }

  // POST /api/staff/question-bank
  public function store(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    $data = $request->validate([
      'subject_id' => 'required|integer',
      'question_text' => 'required|string',
      'option_a' => 'required|string',
      'option_b' => 'required|string',
      'option_c' => 'nullable|string',
      'option_d' => 'nullable|string',
      'correct_option' => 'required|string|in:A,B,C,D',
      'explanation' => 'nullable|string',
      'media' => 'nullable|file|mimes:jpg,jpeg,png,mp4,webm,pdf|max:20480',
      'media_type' => 'nullable|string|in:image,video,formula',
      'source_type' => 'nullable|string|in:manual,ai',
    ]);

    $subject = Subject::where('id', $data['subject_id'])
      ->where('school_id', $schoolId)
      ->first();

    if (!$subject) return response()->json(['message' => 'Invalid subject'], 422);

    $mediaPath = null;
    if ($request->hasFile('media')) {
      $mediaPath = $request->file('media')->store("schools/{$schoolId}/question-bank", 'public');
    }

    $row = QuestionBankQuestion::create([
      'school_id' => $schoolId,
      'teacher_user_id' => $user->id,
      'subject_id' => $subject->id,
      'question_text' => $data['question_text'],
      'option_a' => $data['option_a'],
      'option_b' => $data['option_b'],
      'option_c' => $data['option_c'] ?? null,
      'option_d' => $data['option_d'] ?? null,
      'correct_option' => $data['correct_option'],
      'explanation' => $data['explanation'] ?? null,
      'source_type' => $data['source_type'] ?? 'manual',
      'media_path' => $mediaPath,
      'media_type' => $data['media_type'] ?? null,
    ]);

    return response()->json(['message' => 'Saved', 'data' => $row], 201);
  }

  // POST /api/staff/question-bank/ai-generate
  // Generate objective questions from a statement using an OpenAI-compatible API.
  public function aiGenerate(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    $data = $request->validate([
      'subject_id' => 'required|integer',
      'prompt' => 'required|string|max:2000',
      'count' => 'required|integer|in:3,5,10',
      'import_to_bank' => 'nullable|boolean',
    ]);

    $subject = Subject::where('id', $data['subject_id'])
      ->where('school_id', $schoolId)
      ->first();
    if (!$subject) return response()->json(['message' => 'Invalid subject'], 422);

    $aiBaseUrl = trim((string) config('services.ai.base_url', 'https://api.openai.com/v1'));
    $aiModel = trim((string) config('services.ai.model', 'gpt-4.1-mini'));
    $aiApiKey = trim((string) config('services.ai.api_key', ''));
    $aiCaBundle = trim((string) config('services.ai.ca_bundle', ''));
    // Keep request time below typical proxy/gateway limits to avoid surfacing 504 to the UI.
    $configuredTimeout = (int) config('services.ai.timeout', 45);
    $configuredConnectTimeout = (int) config('services.ai.connect_timeout', 10);
    $aiTimeout = max(5, min($configuredTimeout, 25));
    $aiConnectTimeout = max(3, min($configuredConnectTimeout, 8));

    $chatCompletionsUrl = rtrim($aiBaseUrl, '/') . '/chat/completions';
    $isLocalAiEndpoint = (bool) preg_match('/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?(\/|$)/i', $aiBaseUrl);

    if ($aiApiKey === '' && !$isLocalAiEndpoint) {
      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => 'AI API key missing on server. Generated fallback questions.',
        'fallback' => true,
        'data' => $fallback,
      ]);
    }

    $systemPrompt = "You generate multiple-choice questions from the user's statement.
Return STRICT JSON only:
{
  \"questions\": [
    {
      \"question_text\": \"...\",
      \"option_a\": \"...\",
      \"option_b\": \"...\",
      \"option_c\": \"...\",
      \"option_d\": \"...\",
      \"correct_option\": \"A|B|C|D\",
      \"explanation\": \"...\"
    }
  ]
}
Rules:
- Generate exactly the requested count.
- Every question must be unique. Do not repeat or rephrase the same stem.
- Questions must be based on the statement and subject.
- question_text must NOT include numbering/prefixes.
- Keep options concise and plausible.
- correct_option must be one of A,B,C,D.
- Keep explanation to one short sentence.";

    $userPrompt = "Subject: {$subject->name}\nCount: {$data['count']}\nStatement:\n{$data['prompt']}";

    $http = Http::timeout($aiTimeout)->connectTimeout($aiConnectTimeout);

    $isHttpsEndpoint = str_starts_with(strtolower($chatCompletionsUrl), 'https://');
    $verifyPath = null;
    if ($isHttpsEndpoint) {
      $verifyCandidates = array_values(array_filter([
        $aiCaBundle,
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
        base_path('cacert.pem'),
        'C:\\Users\\EMMY\\AppData\\Local\\Programs\\PHP\\current\\extras\\ssl\\cacert.pem',
      ], fn($v) => is_string($v) && trim($v) !== ''));

      foreach ($verifyCandidates as $cand) {
        if (file_exists($cand)) {
          $verifyPath = $cand;
          break;
        }
      }

      if ($verifyPath) {
        $http = $http->withOptions([
          'verify' => $verifyPath,
          'curl' => [
            CURLOPT_CAINFO => $verifyPath,
          ],
        ]);
      }
    }

    $activeModel = $aiModel;
    $content = '';
    try {
      $requestBuilder = $http->acceptJson();
      if ($aiApiKey !== '') {
        $requestBuilder = $requestBuilder->withToken($aiApiKey);
      }

      $maxTokens = max(250, min(1200, ((int) $data['count']) * 120));
      $response = $requestBuilder
        ->retry(1, 500)
        ->post($chatCompletionsUrl, [
          'model' => $activeModel,
          'temperature' => 0.3,
          'max_tokens' => $maxTokens,
          'stream' => false,
          'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
          ],
        ]);

      // Ollama often exposes models as "<name>:latest". Retry with compatible local tags when needed.
      if ($response->failed() && $isLocalAiEndpoint) {
        [$providerStatus, , $providerMessage] = $this->aiErrorMeta($response);
        $errorLower = strtolower($providerMessage);
        $isLikelyModelIssue = $providerStatus === 404
          || str_contains($errorLower, 'model')
          || str_contains($errorLower, 'not found');

        if ($isLikelyModelIssue) {
          foreach ($this->localModelFallbackCandidates($aiModel) as $candidateModel) {
            if (strcasecmp($candidateModel, $activeModel) === 0) {
              continue;
            }

            $retry = $requestBuilder->post($chatCompletionsUrl, [
              'model' => $candidateModel,
              'temperature' => 0.3,
              'max_tokens' => $maxTokens,
              'stream' => false,
              'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
              ],
            ]);

            if ($retry->successful()) {
              $response = $retry;
              $activeModel = $candidateModel;
              break;
            }
          }
        }

        // Fallback for older/non-OpenAI Ollama routes.
        if ($response->failed()) {
          [$providerStatus, , $providerMessage] = $this->aiErrorMeta($response);
          $errorLower = strtolower($providerMessage);
          $isLikelyCompatIssue = in_array($providerStatus, [404, 405, 501], true)
            || str_contains($errorLower, 'not found')
            || str_contains($errorLower, 'unsupported')
            || str_contains($errorLower, 'route');

          if ($isLikelyCompatIssue) {
            $nativeBase = preg_replace('/\/v1\/?$/i', '', rtrim($aiBaseUrl, '/'));
            $nativeUrl = rtrim((string) $nativeBase, '/') . '/api/chat';
            $nativeCandidates = array_values(array_unique(array_filter([
              $activeModel,
              ...$this->localModelFallbackCandidates($aiModel),
            ])));

            foreach ($nativeCandidates as $candidateModel) {
              $native = $requestBuilder->post($nativeUrl, [
                'model' => $candidateModel,
                'stream' => false,
                'format' => 'json',
                'options' => [
                  'temperature' => 0.3,
                  'num_predict' => $maxTokens,
                ],
                'messages' => [
                  ['role' => 'system', 'content' => $systemPrompt],
                  ['role' => 'user', 'content' => $userPrompt],
                ],
              ]);

              if ($native->successful()) {
                $nativeContent = trim((string) data_get($native->json(), 'message.content', ''));
                if ($nativeContent !== '') {
                  $response = $native;
                  $activeModel = $candidateModel;
                  $content = $nativeContent;
                  break;
                }
              }
            }
          }
        }
      }

      if ($content === '' && $response->successful()) {
        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
      }
    } catch (ConnectionException $e) {
      Log::error('AI provider connection failed', [
        'base_url' => $aiBaseUrl,
        'endpoint' => $chatCompletionsUrl,
        'model' => $activeModel,
        'verify_path' => $verifyPath,
        'message' => $e->getMessage(),
      ]);
      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => 'AI provider unavailable. Generated fallback questions.',
        'fallback' => true,
        'ai_base_url' => $aiBaseUrl,
        'ca_verify' => $verifyPath,
        'error' => $e->getMessage(),
        'data' => $fallback,
      ]);
    } catch (Throwable $e) {
      Log::error('AI provider unexpected failure', [
        'base_url' => $aiBaseUrl,
        'endpoint' => $chatCompletionsUrl,
        'model' => $activeModel,
        'verify_path' => $verifyPath,
        'message' => $e->getMessage(),
      ]);
      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => 'AI provider failed. Generated fallback questions.',
        'fallback' => true,
        'ai_base_url' => $aiBaseUrl,
        'ca_verify' => $verifyPath,
        'error' => $e->getMessage(),
        'data' => $fallback,
      ]);
    }

    if ($response->failed()) {
      [$providerStatus, $errorCode, $providerMessage] = $this->aiErrorMeta($response);

      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => $providerMessage !== '' ? "AI provider failed ({$providerMessage}). Generated fallback questions." : 'AI provider failed. Generated fallback questions.',
        'fallback' => true,
        'code' => $errorCode,
        'provider_status' => $providerStatus ?: null,
        'provider_message' => $providerMessage !== '' ? $providerMessage : null,
        'model' => $activeModel,
        'ca_verify' => $verifyPath ?: null,
        'details' => $response->json(),
        'data' => $fallback,
      ]);
    }

    if ($content === '') {
      $content = (string) data_get($response->json(), 'choices.0.message.content', '');
    }
    $payload = $this->extractJsonObject($content);

    if (!$payload || !is_array($payload['questions'] ?? null)) {
      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => 'AI returned invalid format. Generated fallback questions.',
        'fallback' => true,
        'data' => $fallback,
      ]);
    }

    $generated = collect($payload['questions'])
      ->take((int) $data['count'])
      ->map(function ($q) {
        $correct = strtoupper(trim((string)($q['correct_option'] ?? 'A')));
        if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
          $correct = 'A';
        }

        return [
          'question_text' => $this->stripQuestionPrefix((string)($q['question_text'] ?? '')),
          'option_a' => trim((string)($q['option_a'] ?? '')),
          'option_b' => trim((string)($q['option_b'] ?? '')),
          'option_c' => trim((string)($q['option_c'] ?? '')),
          'option_d' => trim((string)($q['option_d'] ?? '')),
          'correct_option' => $correct,
          'explanation' => trim((string)($q['explanation'] ?? '')),
          'source_type' => 'ai',
        ];
      })
      ->filter(fn($q) => $q['question_text'] !== '' && $q['option_a'] !== '' && $q['option_b'] !== '')
      ->values()
      ->all();

    $generated = $this->withUniqueFallbackFill(
      $generated,
      $subject->name,
      (string) $data['prompt'],
      (int) $data['count']
    );

    if (count($generated) === 0) {
      $fallback = $this->withUniqueFallbackFill([], $subject->name, (string) $data['prompt'], (int) $data['count']);
      if (!empty($data['import_to_bank'])) {
        $this->maybeImportToBank($fallback, (int) $schoolId, (int) $user->id, (int) $subject->id);
      }
      return response()->json([
        'message' => 'AI did not return usable questions. Generated fallback questions.',
        'fallback' => true,
        'data' => $fallback,
      ]);
    }

    if (!empty($data['import_to_bank'])) {
      $this->maybeImportToBank($generated, (int) $schoolId, (int) $user->id, (int) $subject->id);
    }

    return response()->json([
      'message' => 'Generated',
      'data' => $generated,
    ]);
  }

  public function destroy(Request $request, QuestionBankQuestion $question)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    abort_unless((int)$question->school_id === (int)$schoolId, 403);
    abort_unless((int)$question->teacher_user_id === (int)$user->id, 403);

    if ($question->media_path && Storage::disk('public')->exists($question->media_path)) {
      Storage::disk('public')->delete($question->media_path);
    }

    $question->delete();

    return response()->json(['message' => 'Deleted']);
  }
}
