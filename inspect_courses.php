<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$classId = $argv[1] ?? 1;
$termId = $argv[2] ?? 1;

$items = DB::table('subjects')
    ->join('term_subjects','term_subjects.subject_id','=','subjects.id')
    ->leftJoin('users as t','t.id','=','term_subjects.teacher_user_id')
    ->where('term_subjects.class_id',(int)$classId)
    ->where('term_subjects.term_id',(int)$termId)
    ->where('subjects.school_id', 1)
    ->select('subjects.id','subjects.name','term_subjects.id as term_subject_id','term_subjects.teacher_user_id','t.name as teacher_name')
    ->get();

echo $items->toJson(JSON_PRETTY_PRINT);
