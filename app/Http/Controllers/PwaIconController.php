<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PwaIconController extends Controller
{
    public function show(Request $request, int $size)
    {
        abort_unless(in_array($size, [192, 512], true), 404);

        $school = PwaManifestController::resolveTenantSchool($request);
        $source = $school?->logo_path
            ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
            : public_path('lyt-logo.png');

        if (!is_file($source) || !function_exists('imagecreatetruecolor')) {
            return response()->file(public_path("pwa-{$size}.png"), ['Content-Type' => 'image/png']);
        }

        $image = $this->loadImage($source);
        if (!$image) {
            return response()->file(public_path("pwa-{$size}.png"), ['Content-Type' => 'image/png']);
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = min(($size * 0.82) / $sourceWidth, ($size * 0.82) / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        imagecopyresampled($canvas, $image, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store, max-age=0']);
    }

    private function loadImage(string $path): mixed
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }
}