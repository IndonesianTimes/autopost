<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;

class ImageCard
{
    public static function generate(string $title, float $rtp, string $timeLabel): array
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            return ['ok' => false, 'error' => 'gd-not-available'];
        }

        $mediaDir  = Config::get('SAE_MEDIA_DIR', 'public/media/cards');
        $baseUrl   = Config::get('SAE_MEDIA_BASEURL', '/media/cards');
        $root      = dirname(__DIR__, 2);

        if ($mediaDir[0] !== '/' && !preg_match('#^[A-Za-z]:\\\\#', $mediaDir)) {
            $mediaDir = $root . '/' . ltrim($mediaDir, '/');
        }
        if (!is_dir($mediaDir) && !mkdir($mediaDir, 0777, true) && !is_dir($mediaDir)) {
            return ['ok' => false, 'error' => 'mkdir-failed'];
        }

        $hash = substr(sha1($title . '|' . $rtp . '|' . $timeLabel), 0, 12);
        $filename = "card_{$hash}.png";
        $filePath = rtrim($mediaDir, '/') . '/' . $filename;

        if (is_file($filePath)) {
            return ['ok' => true, 'file' => $filePath, 'file_url' => rtrim($baseUrl, '/') . '/' . $filename];
        }

        $img = imagecreatetruecolor(1080, 1080);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 1079, 1079, $white);

        $font = $root . '/assets/Roboto-Regular.ttf';
        if (!is_file($font) || @imagettfbbox(10, 0, $font, 'test') === false) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'font-not-usable'];
        }

        $lines = preg_split("/\n/", wordwrap($title, 20, "\n"));
        if (count($lines) > 3) {
            $lines = array_slice($lines, 0, 3);
            $lines[2] .= '…';
        }
        $y = 150;
        foreach ($lines as $line) {
            imagettftext($img, 48, 0, 60, $y, $black, $font, $line);
            $y += 60;
        }

        $rtpText = 'RTP ' . number_format($rtp, 0) . '%';
        $bbox = imagettfbbox(120, 0, $font, $rtpText);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int) ((1080 - $textWidth) / 2);
        imagettftext($img, 120, 0, $x, 600, $black, $font, $rtpText);

        $bbox = imagettfbbox(32, 0, $font, $timeLabel);
        $tw = $bbox[2] - $bbox[0];
        imagettftext($img, 32, 0, 1080 - $tw - 40, 1040, $black, $font, $timeLabel);

        if (!imagepng($img, $filePath)) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'write-failed'];
        }
        imagedestroy($img);

        return [
            'ok' => true,
            'file' => $filePath,
            'file_url' => rtrim($baseUrl, '/') . '/' . $filename,
        ];
    }
}
