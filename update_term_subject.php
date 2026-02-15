<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$termSubjectId = $argv[1] ?? null;
$teacherId = $argv[2] ?? null;

if (!$termSubjectId) {
    echo "Usage: php update_term_subject.php <term_subject_id> <teacher_user_id>\n";
    exit(1);
}

DB::table('term_subjects')
    ->where('id', (int)$termSubjectId)
    ->update(['teacher_user_id' => $teacherId ? (int)$teacherId : null]);

echo "Updated term_subjects id={$termSubjectId} -> teacher_user_id={$teacherId}\n";
