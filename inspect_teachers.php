<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$schoolId = $argv[1] ?? 1;

$users = DB::table('users')
    ->where('school_id', (int)$schoolId)
    ->whereIn('role', ['staff','teacher'])
    ->select('id','name','email','role')
    ->get();

echo $users->toJson(JSON_PRETTY_PRINT);
