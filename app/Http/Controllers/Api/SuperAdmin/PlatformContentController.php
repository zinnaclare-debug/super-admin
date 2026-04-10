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
            'content_section_title' => ['nullable', 'string', 'max:120'],
            'content_section_intro' => ['nullable', 'string', 'max:1200'],
            'content_todo_items' => ['nullable', 'array'],
            'content_todo_items.*' => ['nullable', 'string', 'max:220'],
        ]);

        return response()->json([
            'message' => 'Platform homepage content saved successfully.',
            'data' => PlatformHomeContent::save($payload),
        ]);
    }
}
