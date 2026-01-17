<?php

namespace App\Services\Document;

use App\Contracts\DocumentGeneratorInterface;
use App\Models\Invoice;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelInvoiceGenerator implements DocumentGeneratorInterface
{
    public function generate(Invoice $invoice): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $row = 1;

        // Header
        $sheet->setCellValue('A' . $row, 'РАХУНОК');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        // Invoice info
        $sheet->setCellValue('A' . $row, 'Номер рахунку:');
        $sheet->setCellValue('B' . $row, $invoice->invoice_number);
        $row++;

        $sheet->setCellValue('A' . $row, 'Дата:');
        $sheet->setCellValue('B' . $row, $invoice->invoice_date->format('d.m.Y'));
        $row += 2;

        // Company info
        $sheet->setCellValue('A' . $row, 'Продавець:');
        $sheet->setCellValue('A' . ($row + 1), $invoice->ourCompany->name);
        $sheet->setCellValue('A' . ($row + 2), 'ЄДРПОУ: ' . $invoice->ourCompany->edrpou_ipn);
        $row += 3;

        $sheet->setCellValue('A' . $row, 'Покупець:');
        $sheet->setCellValue('A' . ($row + 1), $invoice->counterparty->name);
        if ($invoice->counterparty->edrpou_ipn) {
            $sheet->setCellValue('A' . ($row + 2), 'ЄДРПОУ: ' . $invoice->counterparty->edrpou_ipn);
            $row += 3;
        } else {
            $row += 2;
        }

        $row++;

        // Items header
        $headerRow = $row;
        $sheet->setCellValue('A' . $row, 'Назва товару/послуги');
        $sheet->setCellValue('B' . $row, 'Кількість');
        $sheet->setCellValue('C' . $row, 'Од.изм.');
        $sheet->setCellValue('D' . $row, 'Ціна');
        $sheet->setCellValue('E' . $row, 'Сума');

        $this->applyBorderStyle($sheet, 'A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        // Items
        foreach ($invoice->items as $item) {
            $sheet->setCellValue('A' . $row, $item->name);
            $sheet->setCellValue('B' . $row, $item->quantity);
            $sheet->setCellValue('C' . $row, $item->unit);
            $sheet->setCellValue('D' . $row, $item->unit_price);
            $sheet->setCellValue('E' . $row, $item->total);

            $this->applyBorderStyle($sheet, 'A' . $row . ':E' . $row);
            $row++;
        }

        // Totals
        $totalsStartRow = $row;
        $row++;

        $sheet->setCellValue('D' . $row, 'Сума без ПДВ:');
        $sheet->setCellValue('E' . $row, $invoice->subtotal);
        $this->applyBorderStyle($sheet, 'D' . $row . ':E' . $row);
        $row++;

        if ($invoice->with_vat) {
            $sheet->setCellValue('D' . $row, 'ПДВ (20%):');
            $sheet->setCellValue('E' . $row, $invoice->vat_amount);
            $this->applyBorderStyle($sheet, 'D' . $row . ':E' . $row);
            $row++;
        }

        $sheet->setCellValue('D' . $row, 'РАЗОМ:');
        $sheet->setCellValue('E' . $row, $invoice->total);
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);
        $sheet->getStyle('E' . $row)->getFont()->setBold(true);
        $this->applyBorderStyle($sheet, 'D' . $row . ':E' . $row);
        $row += 2;

        // Sum in words
        $sumInWords = NumberToWordsUkrainian::convert((int) $invoice->total);
        $sheet->setCellValue('A' . $row, 'Сумою: ' . $sumInWords . ' гривень');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $row += 2;

        // Comment
        if ($invoice->comment) {
            $sheet->setCellValue('A' . $row, 'Примітка: ' . $invoice->comment);
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $row++;
        }

        // Generate file
        $safeNumber = str_replace(['/', '\\'], '-', $invoice->invoice_number);
        $fileName = 'invoice_' . $safeNumber . '_' . time() . '.xlsx';
        $path = storage_path('app/invoices/' . $fileName);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    public function getExtension(): string
    {
        return 'xlsx';
    }

    public function getMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    private function applyBorderStyle(&$sheet, $range): void
    {
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle($range)->applyFromArray($borderStyle);
    }
}
