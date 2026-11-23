<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class PdfUploadOptimizer
{
    public const MAX_FINAL_BYTES = 2 * 1024 * 1024;

    /**
     * Stores a PDF only when its final downloadable form fits the library limit.
     * Ghostscript preserves selectable text where possible, unlike screenshot-based
     * browser conversion which would make a textbook blurry and inaccessible.
     */
    public static function storeWithinLimit(UploadedFile $file, string $directory): array
    {
        $originalSize = (int) ($file->getSize() ?? 0);
        if ($originalSize <= self::MAX_FINAL_BYTES) {
            return [
                'path' => $file->store($directory, 'public'),
                'size' => $originalSize,
                'optimized' => false,
            ];
        }

        $sourcePath = $file->getRealPath();
        if (!$sourcePath || !is_file($sourcePath)) {
            throw new RuntimeException('The selected PDF could not be read for optimization.');
        }

        $binary = self::ghostscriptBinary();
        if ($binary === null) {
            throw new RuntimeException('This server cannot optimize large PDFs yet. Ask the administrator to install Ghostscript, then try again.');
        }

        $temporaryDirectory = storage_path('app/pdf-optimization');
        if (!is_dir($temporaryDirectory) && !mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
            throw new RuntimeException('Unable to prepare the PDF optimization workspace.');
        }

        $outputPath = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';

        try {
            foreach (['/ebook', '/screen'] as $quality) {
                self::compress($binary, $sourcePath, $outputPath, $quality);
                clearstatcache(true, $outputPath);

                if (is_file($outputPath) && filesize($outputPath) <= self::MAX_FINAL_BYTES) {
                    $storedPath = trim($directory, '/') . '/' . Str::uuid() . '.pdf';
                    $stream = fopen($outputPath, 'rb');
                    if ($stream === false) {
                        throw new RuntimeException('The optimized PDF could not be prepared for storage.');
                    }

                    try {
                        if (!Storage::disk('public')->put($storedPath, $stream)) {
                            throw new RuntimeException('The optimized PDF could not be saved.');
                        }
                    } finally {
                        fclose($stream);
                    }

                    return [
                        'path' => $storedPath,
                        'size' => (int) filesize($outputPath),
                        'optimized' => true,
                    ];
                }
            }
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }

        throw new RuntimeException('This PDF could not be reduced to 2 MB without excessive quality loss. Please split it into smaller PDFs and upload them separately.');
    }

    private static function ghostscriptBinary(): ?string
    {
        $configured = trim((string) env('PDF_OPTIMIZER_BINARY', ''));
        $candidates = array_filter([
            $configured,
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            'C:\\Program Files\\gs\\gs10.00.0\\bin\\gswin64c.exe',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function compress(string $binary, string $sourcePath, string $outputPath, string $quality): void
    {
        if (!function_exists('exec')) {
            throw new RuntimeException('PDF optimization is unavailable because this server does not allow the required process runner.');
        }

        @unlink($outputPath);
        $arguments = [
            $binary,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=' . $quality,
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            '-sOutputFile=' . $outputPath,
            $sourcePath,
        ];
        $command = implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('The PDF optimizer could not process this file. Please upload a standard PDF or split it into smaller files.');
        }
    }
}
