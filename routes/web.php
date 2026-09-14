<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\PwaIconController;

Route::get('/tenant-manifest.webmanifest', [PwaManifestController::class, 'show']);
Route::get('/tenant-pwa-icon/{size}.png', [PwaIconController::class, 'show'])->whereNumber('size');

// Fallback file-serving route for public storage assets.
// This ensures /storage/... works even when app routing would otherwise catch it.
Route::get('/storage/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(404);
    }

    $fullPath = storage_path('app/public/' . ltrim($path, '/'));
    if (!File::exists($fullPath)) {
        abort(404);
    }

    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

Route::get('/{any}', function () {
    $path = public_path('build/index.html');
    if (!File::exists($path)) {
        abort(404, 'Frontend build not found. Run npm run build.');
    }
    return File::get($path);
})->where('any', '^(?!(api|storage)).*$');
