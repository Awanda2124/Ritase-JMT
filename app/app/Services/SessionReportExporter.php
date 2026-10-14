<?php

namespace App\Services;

use App\Models\WorkSession;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SessionReportExporter
{
    private const MONTHS = [
        1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
        7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
    ];

    private const RUPIAH = '"Rp"#,##0';

    public function __construct(private readonly SessionReportBuilder $builder) {}

    public function filename(WorkSession $session): string
    {
        return 'ritase-'.$session->date->format('Y-m-d').'.xlsx';
    }

    public function write(WorkSession $session, string $target): void
    {
        $spreadsheet = $this->spreadsheet($session, $this->builder->build($session));

        (new Xlsx($spreadsheet))->save($target);

        $spreadsheet->disconnectWorksheets();
    }

    /** @param array<string, mixed> $data */
    private function spreadsheet(WorkSession $session, array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ritase');

        // Judul
        $sheet->setCellValue('A1', 'TANGGAL: '.$this->dateRange($session));
        $sheet->setCellValue('A2', mb_strtoupper($session->route));
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');

        // Header dua baris
        foreach (['A' => 'NO', 'B' => 'NAMA', 'C' => 'NO. KTP', 'K' => 'BIAYA', 'L' => 'KETERANGAN'] as $col => $label) {
            $sheet->setCellValue("{$col}3", $label);
            $sheet->mergeCells("{$col}3:{$col}4");
        }
        $sheet->setCellValue('D3', 'RITASE');
        $sheet->mergeCells('D3:J3');
        for ($n = 1; $n <= SessionReportBuilder::MAX_COLUMNS; $n++) {
            $sheet->setCellValue([3 + $n, 4], $n);
        }

        // Data supir
        $row = 5;
        foreach ($data['rows'] as $item) {
            $sheet->setCellValue([1, $row], $item['no']);
            $sheet->setCellValue([2, $row], $item['name']);
            $sheet->setCellValueExplicit([3, $row], $item['ktp'], DataType::TYPE_STRING);

            foreach ($item['chassis'] as $i => $chassis) {
                $sheet->setCellValueExplicit([4 + $i, $row], $chassis, DataType::TYPE_STRING);
            }

            $sheet->setCellValue([11, $row], $item['cost']);
            $sheet->setCellValue([12, $row], $item['note'] ?? '');
            $row++;
        }
        $lastDataRow = $row - 1;

        // Footer
        $ritRow = $row;
        $costRow = $row + 1;
        $sheet->setCellValue("D{$ritRow}", 'TOTAL RIT=');
        $sheet->mergeCells("D{$ritRow}:J{$ritRow}");
        $sheet->setCellValue("K{$ritRow}", $data['total_trips']);
        $sheet->setCellValue("D{$costRow}", 'TOTAL BIAYA DRIVER=');
        $sheet->mergeCells("D{$costRow}:J{$costRow}");
        $sheet->setCellValue("K{$costRow}", $data['total_cost']);

        // Peringatan kalau masih ada yang belum dicek
        if ($data['pending'] > 0) {
            $warnRow = $costRow + 2;
            $sheet->setCellValue("A{$warnRow}", "PERHATIAN: {$data['pending']} laporan masih menunggu pengecekan dan belum dihitung.");
            $sheet->mergeCells("A{$warnRow}:L{$warnRow}");
            $sheet->getStyle("A{$warnRow}")->getFont()->setBold(true)->getColor()->setARGB('FFC00000');
        }

        $this->style($sheet, $lastDataRow, $ritRow, $costRow);

        return $spreadsheet;
    }

    private function style(Worksheet $sheet, int $lastDataRow, int $ritRow, int $costRow): void
    {
        $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];

        $sheet->getStyle('A1:L4')->applyFromArray($center + ['font' => ['bold' => true]]);
        $sheet->getStyle('A1')->getFont()->setSize(12);
        $sheet->getStyle('A2')->getFont()->setSize(14);

        $sheet->getStyle("A3:L{$costRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        if ($lastDataRow >= 5) {
            $sheet->getStyle("A5:A{$lastDataRow}")->applyFromArray($center);
            $sheet->getStyle("D5:J{$lastDataRow}")->applyFromArray($center);
            $sheet->getStyle("K5:K{$lastDataRow}")->getNumberFormat()->setFormatCode(self::RUPIAH);
        }

        $sheet->getStyle("D{$ritRow}:K{$costRow}")->getFont()->setBold(true);
        $sheet->getStyle("D{$ritRow}:J{$costRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("K{$costRow}")->getNumberFormat()->setFormatCode(self::RUPIAH);

        foreach (['A' => 5, 'B' => 28, 'C' => 22, 'K' => 14, 'L' => 32] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        foreach (range('D', 'J') as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
    }

    /** Langsiran lewat tengah malam: tanggal sesi sampai hari berikutnya. */
    private function dateRange(WorkSession $session): string
    {
        $start = $session->date->copy();
        $end = $start->copy()->addDay();

        $startMonth = self::MONTHS[$start->month];
        $endMonth = self::MONTHS[$end->month];

        if ($start->year !== $end->year) {
            return sprintf('%d %s %d-%d %s %d', $start->day, $startMonth, $start->year, $end->day, $endMonth, $end->year);
        }

        if ($start->month !== $end->month) {
            return sprintf('%d %s-%d %s %d', $start->day, $startMonth, $end->day, $endMonth, $end->year);
        }

        return sprintf('%d-%d %s %d', $start->day, $end->day, $startMonth, $start->year);
    }
}
