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
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);

        $row = 1;

        // Header
        $sheet->setCellValue('A' . $row, 'РАХУНОК');
        $sheet->mergeCells('A' . $row . ':G' . $row);
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
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, $invoice->ourCompany->name);
        $row++;
        $sheet->setCellValue('A' . $row, 'ЄДРПОУ/ІПН: ' . $invoice->ourCompany->edrpou_ipn);
        $row++;

        if ($invoice->ourCompany->address) {
            $sheet->setCellValue('A' . $row, 'Адреса: ' . $invoice->ourCompany->address);
            $row++;
        }

        if ($invoice->ourCompany->phone) {
            $sheet->setCellValue('A' . $row, 'Телефон: ' . $invoice->ourCompany->phone);
            $row++;
        }

        if ($invoice->ourCompany->email) {
            $sheet->setCellValue('A' . $row, 'Email: ' . $invoice->ourCompany->email);
            $row++;
        }

        $row++;

        // Counterparty info
        $sheet->setCellValue('A' . $row, 'Покупець:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, $invoice->counterparty->name);
        $row++;

        if ($invoice->counterparty->edrpou_ipn) {
            $sheet->setCellValue('A' . $row, 'ЄДРПОУ/ІПН: ' . $invoice->counterparty->edrpou_ipn);
            $row++;
        }

        if ($invoice->counterparty->address) {
            $sheet->setCellValue('A' . $row, 'Адреса: ' . $invoice->counterparty->address);
            $row++;
        }

        if ($invoice->counterparty->phone) {
            $sheet->setCellValue('A' . $row, 'Телефон: ' . $invoice->counterparty->phone);
            $row++;
        }

        if ($invoice->counterparty->email) {
            $sheet->setCellValue('A' . $row, 'Email: ' . $invoice->counterparty->email);
            $row++;
        }

        $row++;

        // Add delivery section if available
        if ($invoice->order && ($invoice->order->delivery_name || $invoice->order->delivery_address)) {
            $sheet->setCellValue('A' . $row, 'Доставка:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            if ($invoice->order->delivery_name) {
                $sheet->setCellValue('A' . $row, 'Одержувач: ' . $invoice->order->delivery_name);
                $row++;
            }

            if ($invoice->order->delivery_city) {
                $sheet->setCellValue('A' . $row, 'Місто: ' . $invoice->order->delivery_city);
                $row++;
            }

            if ($invoice->order->delivery_address) {
                $sheet->setCellValue('A' . $row, 'Адреса: ' . $invoice->order->delivery_address);
                $row++;
            }

            if ($invoice->order->delivery_type) {
                $sheet->setCellValue('A' . $row, 'Тип доставки: ' . $invoice->order->delivery_type);
                $row++;
            }

            $row++;
        }

        // Items header
        $headerRow = $row;
        $sheet->setCellValue('A' . $row, 'Назва товару/послуги');
        $sheet->setCellValue('B' . $row, 'Кількість');
        $sheet->setCellValue('C' . $row, 'Од.изм.');
        $sheet->setCellValue('D' . $row, 'Ціна');
        $sheet->setCellValue('E' . $row, 'Підсумок');
        $sheet->setCellValue('F' . $row, 'Знижка');
        $sheet->setCellValue('G' . $row, 'Сума');

        $this->applyBorderStyle($sheet, 'A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        // Items
        foreach ($invoice->items as $item) {
            $sheet->setCellValue('A' . $row, $item->name);
            $sheet->setCellValue('B' . $row, $item->quantity);
            $sheet->setCellValue('C' . $row, $item->unit);
            $sheet->setCellValue('D' . $row, $item->unit_price);
            $sheet->setCellValue('E' . $row, $item->subtotal);

            // Format discount display
            $discountDisplay = '';
            if ($item->discount_amount > 0) {
                $discountDisplay = number_format($item->discount_amount, 2) . ' грн';
                if ($item->discount_type === 'percent') {
                    $discountDisplay .= ' (' . number_format($item->discount_value, 2) . '%)';
                }
            } else {
                $discountDisplay = '—';
            }
            $sheet->setCellValue('F' . $row, $discountDisplay);
            $sheet->setCellValue('G' . $row, $item->total);

            $this->applyBorderStyle($sheet, 'A' . $row . ':G' . $row);
            $row++;
        }

        // Totals
        $totalsStartRow = $row;
        $row++;

        $sheet->setCellValue('F' . $row, 'Сума без ПДВ:');
        $sheet->setCellValue('G' . $row, $invoice->subtotal);
        $this->applyBorderStyle($sheet, 'F' . $row . ':G' . $row);
        $row++;

        if ($invoice->with_vat) {
            $sheet->setCellValue('F' . $row, 'ПДВ (20%):');
            $sheet->setCellValue('G' . $row, $invoice->vat_amount);
            $this->applyBorderStyle($sheet, 'F' . $row . ':G' . $row);
            $row++;
        }

        $sheet->setCellValue('F' . $row, 'РАЗОМ:');
        $sheet->setCellValue('G' . $row, $invoice->total);
        $sheet->getStyle('F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('G' . $row)->getFont()->setBold(true);
        $this->applyBorderStyle($sheet, 'F' . $row . ':G' . $row);
        $row += 2;

        // Sum in words
        $sumInWords = NumberToWordsUkrainian::convert((int) $invoice->total);
        $sheet->setCellValue('A' . $row, 'Сумою: ' . $sumInWords . ' гривень');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $row += 2;

        // Comment
        if ($invoice->comment) {
            $sheet->setCellValue('A' . $row, 'Примітка: ' . $invoice->comment);
            $sheet->mergeCells('A' . $row . ':G' . $row);
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
