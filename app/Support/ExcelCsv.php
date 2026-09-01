<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelCsv
{
    /**
     * UTF-8 CSV with BOM so Excel opens columns correctly.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'export.csv';
        if (! str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $cells = array_map(function ($v) {
                    if (is_bool($v)) {
                        return $v ? '1' : '0';
                    }

                    return (string) $v;
                }, is_array($row) ? $row : iterator_to_array($row));
                fputcsv($out, $cells);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
