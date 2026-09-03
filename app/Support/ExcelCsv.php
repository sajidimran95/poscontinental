<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelCsv
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows, string $sheetTitle = 'Export'): BinaryFileResponse
    {
        [$path, $filename] = self::writeTemp($filename, $headers, $rows, $sheetTitle);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function queue(string $filename, array $headers, iterable $rows, string $sheetTitle = 'Export'): string
    {
        [$path, $filename] = self::writeTemp($filename, $headers, $rows, $sheetTitle);
        $token = (string) Str::uuid();
        Cache::put('xlsx_dl:'.$token, [
            'path' => $path,
            'name' => $filename,
            'user' => (int) auth()->id(),
        ], 180);

        return $token;
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function writeTemp(string $filename, array $headers, iterable $rows, string $sheetTitle = 'Export'): array
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'export.xlsx';
        $filename = preg_replace('/\.csv$/i', '.xlsx', $filename) ?: $filename;
        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $sheetTitle = mb_substr(preg_replace('/[\\/:?*\[\]]+/', ' ', $sheetTitle) ?: 'Export', 0, 31);
        $colCount = max(1, count($headers));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        foreach ($headers as $i => $label) {
            $cell = Coordinate::stringFromColumnIndex($i + 1).'1';
            $sheet->setCellValueExplicit($cell, (string) $label, DataType::TYPE_STRING);
        }

        $r = 2;
        foreach ($rows as $row) {
            $cells = is_array($row) ? array_values($row) : iterator_to_array($row);
            for ($c = 0; $c < $colCount; $c++) {
                $val = $cells[$c] ?? '';
                if (is_bool($val)) {
                    $val = $val ? 'Yes' : 'No';
                }
                $str = is_scalar($val) ? (string) $val : '';
                $coord = Coordinate::stringFromColumnIndex($c + 1).$r;
                $sheet->setCellValueExplicit($coord, $str, DataType::TYPE_STRING);
            }
            $r++;
        }

        $lastRow = max(1, $r - 1);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $headerRange = 'A1:'.$lastCol.'1';
        $tableRange = 'A1:'.$lastCol.$lastRow;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
        ]);

        if ($lastRow >= 2) {
            $sheet->getStyle('A2:'.$lastCol.$lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
            ]);
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 === 1) {
                    $sheet->getStyle('A'.$row.':'.$lastCol.$row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('E8EEF7');
                }
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter($tableRange);
        $sheet->getRowDimension(1)->setRowHeight(22);
        for ($c = 1; $c <= $colCount; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xlsx-'.Str::uuid()->toString().'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmp);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [$tmp, $filename];
    }
}
