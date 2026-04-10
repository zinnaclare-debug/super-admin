<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlatformHomeContent;

class PublicPlatformContentController extends Controller
{
    public function show()
    {
        return response()->json([
            'data' => PlatformHomeContent::load(),
        ]);
    }
}
