<?php

namespace App\Support;

/**
 * Minimal Code 128 (Set B) barcode renderer for DomPDF (HTML bar spans).
 */
class Code128Barcode
{
    /** @var array<int, string> Pattern of bar/space widths (B=bar, S=space) as digit string */
    private const PATTERNS = [
        0 => '212222', 1 => '222122', 2 => '222221', 3 => '121223', 4 => '121322',
        5 => '131222', 6 => '122213', 7 => '122312', 8 => '132212', 9 => '221213',
        10 => '221312', 11 => '231212', 12 => '112232', 13 => '122132', 14 => '122231',
        15 => '113222', 16 => '123122', 17 => '123221', 18 => '223211', 19 => '221132',
        20 => '221231', 21 => '213212', 22 => '223112', 23 => '312131', 24 => '311222',
        25 => '321122', 26 => '321221', 27 => '312212', 28 => '322112', 29 => '322211',
        30 => '212123', 31 => '212321', 32 => '232121', 33 => '111323', 34 => '131123',
        35 => '131321', 36 => '112313', 37 => '132113', 38 => '132311', 39 => '211313',
        40 => '231113', 41 => '231311', 42 => '112133', 43 => '112331', 44 => '132131',
        45 => '113123', 46 => '113321', 47 => '133121', 48 => '313121', 49 => '211331',
        50 => '231131', 51 => '213113', 52 => '213311', 53 => '213131', 54 => '311123',
        55 => '311321', 56 => '331121', 57 => '312113', 58 => '312311', 59 => '332111',
        60 => '314111', 61 => '221411', 62 => '431111', 63 => '111224', 64 => '111422',
        65 => '121124', 66 => '121421', 67 => '141122', 68 => '141221', 69 => '112214',
        70 => '112412', 71 => '122114', 72 => '122411', 73 => '142112', 74 => '142211',
        75 => '241211', 76 => '221114', 77 => '413111', 78 => '241112', 79 => '134111',
        80 => '111242', 81 => '121142', 82 => '121241', 83 => '114212', 84 => '124112',
        85 => '124211', 86 => '411212', 87 => '421112', 88 => '421211', 89 => '212141',
        90 => '214121', 91 => '412121', 92 => '111143', 93 => '111341', 94 => '131141',
        95 => '114113', 96 => '114311', 97 => '411113', 98 => '411311', 99 => '113141',
        100 => '114131', 101 => '311141', 102 => '411131', 103 => '211412', 104 => '211214',
        105 => '211232', 106 => '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * Render Code 128B as a single PNG data-URI (fast for DomPDF vs hundreds of spans).
     */
    public static function dataUri(string $text, int $moduleWidth = 2, int $height = 44): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }

        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?: '0';
        $codes = [self::START_B];
        $checksum = self::START_B;

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $value = ord($text[$i]) - 32;
            $codes[] = $value;
            $checksum += $value * ($i + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        $totalModules = 0;
        $segments = [];
        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code] ?? self::PATTERNS[0];
            $black = true;
            foreach (str_split($pattern) as $digit) {
                $w = ((int) $digit) * $moduleWidth;
                $segments[] = [$black, $w];
                $totalModules += $w;
                $black = ! $black;
            }
        }

        $width = max(1, $totalModules);
        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return '';
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $blackColor = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $x = 0;
        foreach ($segments as [$isBlack, $w]) {
            if ($isBlack) {
                imagefilledrectangle($img, $x, 0, $x + $w - 1, $height - 1, $blackColor);
            }
            $x += $w;
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        if ($png === false || $png === '') {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Render Code 128B as DomPDF-safe inline HTML bars.
     */
    public static function html(string $text, int $moduleWidth = 1, int $height = 38, string $align = 'right'): string
    {
        $uri = self::dataUri($text, max(1, $moduleWidth), $height);
        if ($uri !== '') {
            $alignCss = in_array($align, ['left', 'center', 'right'], true) ? $align : 'right';

            return '<div style="text-align:'.$alignCss.';line-height:0;font-size:0;">'
                .'<img src="'.$uri.'" alt="" height="'.$height.'" style="height:'.$height.'px;" />'
                .'</div>';
        }

        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?: '0';
        $codes = [self::START_B];
        $checksum = self::START_B;

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $value = ord($text[$i]) - 32;
            $codes[] = $value;
            $checksum += $value * ($i + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        $alignCss = in_array($align, ['left', 'center', 'right'], true) ? $align : 'right';
        $html = '<div style="font-size:0;line-height:0;white-space:nowrap;text-align:'.$alignCss.';">';
        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code] ?? self::PATTERNS[0];
            $black = true;
            $digits = str_split($pattern);
            foreach ($digits as $digit) {
                $w = ((int) $digit) * $moduleWidth;
                $color = $black ? '#000000' : '#ffffff';
                $html .= '<span style="display:inline-block;width:'.$w.'px;height:'.$height.'px;background:'.$color.';"></span>';
                $black = ! $black;
            }
        }
        $html .= '</div>';

        return $html;
    }
}
