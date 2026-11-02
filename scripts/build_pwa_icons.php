<?php

declare(strict_types=1);

// Build application icons from the same Cape Tennis artwork used on the site.
$source = imagecreatefrompng(dirname(__DIR__).'/public/assets/img/logos/cape-tennis-logo-transparent.png');
$width = imagesx($source);
$height = imagesy($source);

foreach ([48, 192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    // Keep the full round logo inside a maskable icon's central safe circle.
    $scale = ($size * 0.78) / max($width, $height);
    $targetWidth = (int) round($width * $scale);
    $targetHeight = (int) round($height * $scale);
    imagecopyresampled($image, $source, (int) (($size - $targetWidth) / 2), (int) (($size - $targetHeight) / 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
    if ($size === 48) {
        ob_start();
        imagepng($image, null, 9);
        $png = ob_get_clean();
        // ICO supports a PNG payload; keep direct /favicon.ico requests branded too.
        $header = pack('vvv', 0, 1, 1);
        $entry = pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), 22);
        file_put_contents(dirname(__DIR__).'/public/favicon.ico', $header.$entry.$png);
    } else {
        imagepng($image, dirname(__DIR__)."/public/assets/img/pwa/cape-tennis-app-{$size}.png", 9);
    }
    imagedestroy($image);
}

imagedestroy($source);

// An SVG loaded as an image cannot fetch another image; embed its PNG payload.
$png = base64_encode(file_get_contents(dirname(__DIR__).'/public/assets/img/pwa/cape-tennis-app-512.png'));
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="Cape Tennis">'
    .'<image href="data:image/png;base64,'.$png.'" width="512" height="512" /></svg>';
file_put_contents(dirname(__DIR__).'/public/assets/img/pwa/cape-tennis-app.svg', $svg."\n");
