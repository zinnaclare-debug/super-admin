<?php

namespace App\Support;

use App\Models\GeneratedDocument;

class GeneratedDocumentData
{
    public static function payload(GeneratedDocument $generatedDocument): array
    {
        return [
            'id' => (int) $generatedDocument->id,
            'type' => (string) $generatedDocument->type,
            'status' => (string) $generatedDocument->status,
            'file_name' => $generatedDocument->file_name,
            'error_message' => $generatedDocument->error_message,
            'can_download' => $generatedDocument->status === GeneratedDocument::STATUS_COMPLETED && !empty($generatedDocument->file_path),
            'created_at' => optional($generatedDocument->created_at)->toIso8601String(),
            'started_at' => optional($generatedDocument->started_at)->toIso8601String(),
            'completed_at' => optional($generatedDocument->completed_at)->toIso8601String(),
        ];
    }
}
