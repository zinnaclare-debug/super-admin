<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\PlatformHomeContent;
use Illuminate\Http\Request;

class PlatformContentController extends Controller
{
    public function show()
    {
        return response()->json([
            'data' => PlatformHomeContent::load(),
        ]);
    }

    public function update(Request $request)
    {
        $payload = $request->validate([
            'about_text' => ['nullable', 'string', 'max:3000'],
            'vision_text' => ['nullable', 'string', 'max:3000'],
            'mission_text' => ['nullable', 'string', 'max:3000'],
        ]);

        return response()->json([
            'message' => 'Platform homepage content saved successfully.',
            'data' => PlatformHomeContent::save($payload),
        ]);
    }
}
