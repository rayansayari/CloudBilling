<?php

namespace App\Service;

use App\Entity\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twig\Environment;

class InvoiceExportService
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function exportPdf(Invoice $invoice): string
    {
        $html = $this->twig->render('invoice/pdf.html.twig', ['invoice' => $invoice]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function exportExcel(Invoice $invoice): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facture');

        // Header
        $sheet->setCellValue('A1', 'Facture: ' . $invoice->getReference());
        $sheet->setCellValue('A2', 'Projet: ' . $invoice->getProject()->getName());
        $sheet->setCellValue('A3', 'Société: ' . $invoice->getProject()->getCompany()->getName());
        $sheet->setCellValue('A4', 'Période: ' . $invoice->getMonthName() . ' ' . $invoice->getYear());
        $sheet->setCellValue('A5', 'Statut: ' . $invoice->getStatus());

        // Table header
        $row = 7;
        $headers = ['Ressource', 'Type de Service', 'Unité', 'Prix Unitaire', 'Quantité Consommée', 'Total Ligne'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }

        // Data
        $row = 8;
        foreach ($invoice->getInvoiceLines() as $line) {
            $sheet->setCellValueByColumnAndRow(1, $row, $line->getResourceName());
            $sheet->setCellValueByColumnAndRow(2, $row, $line->getServiceType());
            $sheet->setCellValueByColumnAndRow(3, $row, $line->getUnit());
            $sheet->setCellValueByColumnAndRow(4, $row, (float)$line->getUnitPrice());
            $sheet->setCellValueByColumnAndRow(5, $row, (float)$line->getConsumedQuantity());
            $sheet->setCellValueByColumnAndRow(6, $row, (float)$line->getLineTotal());
            $row++;
        }

        // Totals
        $row++;
        $sheet->setCellValueByColumnAndRow(5, $row, 'Total HT:');
        $sheet->setCellValueByColumnAndRow(6, $row, (float)$invoice->getTotalHT());
        $row++;
        $sheet->setCellValueByColumnAndRow(5, $row, 'TVA (' . $invoice->getTvaRate() . '%):');
        $sheet->setCellValueByColumnAndRow(6, $row, (float)$invoice->getTotalTVA());
        $row++;
        $sheet->setCellValueByColumnAndRow(5, $row, 'Total TTC:');
        $sheet->setCellValueByColumnAndRow(6, $row, (float)$invoice->getTotalTTC());

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'invoice_');
        $writer->save($temp);
        $content = file_get_contents($temp);
        unlink($temp);

        return $content;
    }
    public function generateConsolidatedPdf($company, int $year, int $month, array $consolidatedData, float $grandTotalHT, float $grandTotalTVA, float $grandTotalTTC): string
    {
        $html = $this->twig->render('consolidated/pdf.html.twig', [
            'company' => $company,
            'year' => $year,
            'month' => $month,
            'consolidatedData' => $consolidatedData,
            'grandTotalHT' => $grandTotalHT,
            'grandTotalTVA' => $grandTotalTVA,
            'grandTotalTTC' => $grandTotalTTC,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
