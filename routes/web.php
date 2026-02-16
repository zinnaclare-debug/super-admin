<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    $path = public_path('build/index.html');
    if (!File::exists($path)) {
        abort(404, 'Frontend build not found. Run npm run build.');
    }
    return File::get($path);
})->where('any', '^(?!api).*$');
