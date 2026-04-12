<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Support\GeneratedDocumentData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GeneratedDocumentController extends Controller
{
    public function show(Request $request, GeneratedDocument $generatedDocument)
    {
        $this->authorizeDocument($request, $generatedDocument);
        $generatedDocument->refresh();

        return response()->json([
            'data' => GeneratedDocumentData::payload($generatedDocument),
        ]);
    }

    public function download(Request $request, GeneratedDocument $generatedDocument)
    {
        $this->authorizeDocument($request, $generatedDocument);

        if ($generatedDocument->status !== GeneratedDocument::STATUS_COMPLETED || !$generatedDocument->file_path) {
            return response()->json(['message' => 'Document is still processing.'], 409);
        }

        $disk = (string) ($generatedDocument->disk ?: 'local');
        if (!Storage::disk($disk)->exists($generatedDocument->file_path)) {
            return response()->json(['message' => 'Generated file not found.'], 404);
        }

        return Storage::disk($disk)->download(
            $generatedDocument->file_path,
            $generatedDocument->file_name ?: 'generated_document.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function authorizeDocument(Request $request, GeneratedDocument $generatedDocument): void
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'school_admin', 403);

        abort_if(
            (int) $generatedDocument->requested_by_user_id !== (int) $user->id
            || (int) $generatedDocument->school_id !== (int) $user->school_id,
            404,
            'Document not found.'
        );
    }
}
