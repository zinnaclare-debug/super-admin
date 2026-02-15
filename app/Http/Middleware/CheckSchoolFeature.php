<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSchoolFeature
{
   public function handle($request, Closure $next, $feature)
{
    $schoolId = $request->user()->school_id;

    $enabled = \App\Models\SchoolFeature::where('school_id', $schoolId)
        ->where('feature', $feature)
        ->where('enabled', true)
        ->exists();

    abort_unless($enabled, 403, 'Feature disabled');

    return $next($request);
}

}
