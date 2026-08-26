<?php
$dir = __DIR__;
function makeSaleIcon(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $clear);
    $teal = imagecolorallocate($im, 15, 118, 110);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $teal);
    $font = 5;
    $text = 'S';
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    $tmp = imagecreatetruecolor($tw + 4, $th + 4);
    imagesavealpha($tmp, true);
    $tClear = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    imagefill($tmp, 0, 0, $tClear);
    $tWhite = imagecolorallocate($tmp, 255, 255, 255);
    imagestring($tmp, $font, 2, 2, $text, $tWhite);
    $targetW = (int) round($size * 0.42);
    $targetH = (int) round($targetW * (($th + 4) / ($tw + 4)));
    $dstX = (int) (($size - $targetW) / 2);
    $dstY = (int) (($size - $targetH) / 2);
    imagecopyresampled($im, $tmp, $dstX, $dstY, 0, 0, $targetW, $targetH, $tw + 4, $th + 4);
    imagedestroy($tmp);
    imagepng($im, $path, 6);
    imagedestroy($im);
}
makeSaleIcon(192, $dir.'/sale-icon-192.png');
makeSaleIcon(512, $dir.'/sale-icon-512.png');
echo "ok\n";
