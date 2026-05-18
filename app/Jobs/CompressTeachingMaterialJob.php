<?php

namespace App\Jobs;

use App\Models\TeachingMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompressTeachingMaterialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    private const TARGET_BYTES = 153600;

    public function __construct(public int $teachingMaterialId)
    {
    }

    public function handle(): void
    {
        $material = TeachingMaterial::query()->find($this->teachingMaterialId);
        if (!$material) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$material->file_path || !$disk->exists($material->file_path)) {
            $material->forceFill([
                'status' => TeachingMaterial::STATUS_FAILED,
                'processing_note' => 'Uploaded file could not be found.',
            ])->save();
            return;
        }

        try {
            $path = $disk->path($material->file_path);
            $originalSize = @filesize($path) ?: (int) $material->file_size;
            $note = $originalSize <= self::TARGET_BYTES
                ? 'File is already within the 150KB target.'
                : 'Compression target is 150KB. File kept in best available quality.';

            if ($originalSize > self::TARGET_BYTES && str_contains((string) $material->mime_type, 'pdf')) {
                $compressed = $this->compressPdfWithGhostscript($path);
                if ($compressed && is_file($compressed)) {
                    $compressedSize = @filesize($compressed) ?: 0;
                    if ($compressedSize > 0 && $compressedSize < $originalSize) {
                        @copy($compressed, $path);
                        $note = $compressedSize <= self::TARGET_BYTES
                            ? 'PDF compressed successfully below the 150KB target.'
                            : 'PDF compressed, but could not safely reach 150KB.';
                    }
                    @unlink($compressed);
                }
            } elseif ($originalSize > self::TARGET_BYTES) {
                $note = 'DOC/DOCX compression is not safely available on the server; original file was kept.';
            }

            clearstatcache(true, $path);
            $material->forceFill([
                'status' => TeachingMaterial::STATUS_READY,
                'file_size' => $originalSize,
                'compressed_size' => @filesize($path) ?: $originalSize,
                'processing_note' => $note,
            ])->save();
        } catch (Throwable $e) {
            $material->forceFill([
                'status' => TeachingMaterial::STATUS_FAILED,
                'processing_note' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    private function compressPdfWithGhostscript(string $path): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $binary = $this->ghostscriptBinary();
        if (!$binary) {
            return null;
        }

        $output = $path . '.compressed.pdf';
        $command = escapeshellarg($binary)
            . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook'
            . ' -dNOPAUSE -dQUIET -dBATCH'
            . ' -sOutputFile=' . escapeshellarg($output)
            . ' ' . escapeshellarg($path);

        $exitCode = 1;
        @exec($command, $lines, $exitCode);

        return $exitCode === 0 && is_file($output) ? $output : null;
    }

    private function ghostscriptBinary(): ?string
    {
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['gswin64c', 'gswin32c', 'gs']
            : ['gs'];

        foreach ($candidates as $candidate) {
            $command = PHP_OS_FAMILY === 'Windows'
                ? 'where ' . escapeshellarg($candidate)
                : 'command -v ' . escapeshellarg($candidate);
            $exitCode = 1;
            $output = [];
            @exec($command, $output, $exitCode);
            if ($exitCode === 0 && !empty($output[0])) {
                return trim((string) $output[0]);
            }
        }

        return null;
    }
}
