<?php

declare(strict_types=1);

function drawBezier($image, array $points, int $colour): void
{
    $previous = $points[0];
    for ($step = 1; $step <= 40; $step++) {
        $t = $step / 40;
        $inverse = 1 - $t;
        $current = [
            ($inverse ** 3 * $points[0][0]) + (3 * $inverse ** 2 * $t * $points[1][0]) + (3 * $inverse * $t ** 2 * $points[2][0]) + ($t ** 3 * $points[3][0]),
            ($inverse ** 3 * $points[0][1]) + (3 * $inverse ** 2 * $t * $points[1][1]) + (3 * $inverse * $t ** 2 * $points[2][1]) + ($t ** 3 * $points[3][1]),
        ];
        imageline($image, (int) $previous[0], (int) $previous[1], (int) $current[0], (int) $current[1], $colour);
        $previous = $current;
    }
}

foreach ([192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);
    imageantialias($image, true);

    $blue = imagecolorallocate($image, 18, 53, 143);
    $orange = imagecolorallocate($image, 249, 160, 27);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $blue);

    $padding = (int) round($size * .17);
    imagefilledellipse($image, $size / 2, $size / 2, $size - (2 * $padding), $size - (2 * $padding), $orange);
    imagesetthickness($image, max(5, (int) round($size * .035)));
    imageellipse($image, $size / 2, $size / 2, $size - (2 * $padding), $size - (2 * $padding), $white);
    imagesetthickness($image, max(5, (int) round($size * .035)));
    $scale = $size / 512;
    drawBezier($image, [[139 * $scale, 139 * $scale], [216 * $scale, 172 * $scale], [340 * $scale, 296 * $scale], [373 * $scale, 373 * $scale]], $white);
    drawBezier($image, [[373 * $scale, 139 * $scale], [340 * $scale, 216 * $scale], [216 * $scale, 340 * $scale], [139 * $scale, 373 * $scale]], $white);

    $label = 'CT';
    $font = 5;
    $textWidth = imagefontwidth($font) * strlen($label);
    imagefilledrectangle($image, ($size - $textWidth) / 2 - 8, $padding - 5, ($size + $textWidth) / 2 + 8, $padding + imagefontheight($font) + 5, $blue);
    imagestring($image, $font, (int) (($size - $textWidth) / 2), $padding, $label, $white);

    imagepng($image, dirname(__DIR__)."/public/assets/img/pwa/cape-tennis-app-{$size}.png", 9);
    imagedestroy($image);
}
