<?php

namespace App\Service;

use App\Entity\FinancialOffer;
use App\Entity\OfferLine;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class FinancialOfferParser
{
    /**
     * Parse an uploaded file (Excel or PDF) and extract offer lines.
     * @return OfferLine[]
     */
    public function parse(string $filePath, FinancialOffer $offer): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx', 'xls', 'csv' => $this->parseExcel($filePath, $offer),
            'pdf' => $this->parsePdf($filePath, $offer),
            default => throw new \InvalidArgumentException("Format non supporté : $extension"),
        };
    }

    private function parseExcel(string $filePath, FinancialOffer $offer): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $lines = [];
        $headerRow = null;
        $columnMap = [];

        // Try to auto-detect header row
        foreach ($rows as $rowIndex => $row) {
            $rowStr = strtolower(implode(' ', array_filter($row)));
            if (str_contains($rowStr, 'ressource') || str_contains($rowStr, 'resource') ||
                str_contains($rowStr, 'service') || str_contains($rowStr, 'désignation') ||
                str_contains($rowStr, 'description')) {
                $headerRow = $rowIndex;
                foreach ($row as $col => $cell) {
                    $cell = strtolower(trim($cell ?? ''));
                    if (str_contains($cell, 'ressource') || str_contains($cell, 'resource') || str_contains($cell, 'désignation')) {
                        $columnMap['resource'] = $col;
                    } elseif (str_contains($cell, 'type') || str_contains($cell, 'service') || str_contains($cell, 'catégorie')) {
                        $columnMap['serviceType'] = $col;
                    } elseif (str_contains($cell, 'prix') || str_contains($cell, 'price') || str_contains($cell, 'tarif') || str_contains($cell, 'pu') || str_contains($cell, 'p.u')) {
                        $columnMap['unitPrice'] = $col;
                    } elseif (str_contains($cell, 'unité') || str_contains($cell, 'unit')) {
                        $columnMap['unit'] = $col;
                    } elseif (str_contains($cell, 'quantité') || str_contains($cell, 'qty') || str_contains($cell, 'qté')) {
                        $columnMap['quantity'] = $col;
                    } elseif (str_contains($cell, 'description') || str_contains($cell, 'détail')) {
                        $columnMap['description'] = $col;
                    }
                }
                break;
            }
        }

        // Fallback: assume first row is header with columns A-F
        if (!$headerRow) {
            $headerRow = 1;
            $cols = array_keys($rows[1] ?? []);
            $columnMap = [
                'resource' => $cols[0] ?? 'A',
                'serviceType' => $cols[1] ?? 'B',
                'unit' => $cols[2] ?? 'C',
                'unitPrice' => $cols[3] ?? 'D',
                'quantity' => $cols[4] ?? 'E',
                'description' => $cols[5] ?? 'F',
            ];
        }

        // Parse data rows
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex <= $headerRow) continue;
            
            $resourceName = trim($row[$columnMap['resource'] ?? 'A'] ?? '');
            if (empty($resourceName)) continue;

            $unitPrice = $this->parseNumber($row[$columnMap['unitPrice'] ?? 'D'] ?? '0');
            if ($unitPrice === '0' || $unitPrice === '0.0000') continue; // Skip rows with no price

            $line = new OfferLine();
            $line->setResourceName($resourceName);
            $line->setServiceType(trim($row[$columnMap['serviceType'] ?? 'B'] ?? '') ?: null);
            $line->setUnit(trim($row[$columnMap['unit'] ?? 'C'] ?? '') ?: 'Unité');
            $line->setUnitPrice($unitPrice);
            $line->setQuantity($this->parseNumber($row[$columnMap['quantity'] ?? 'E'] ?? '0') ?: null);
            $line->setDescription(trim($row[$columnMap['description'] ?? 'F'] ?? '') ?: null);
            $line->setFinancialOffer($offer);

            $lines[] = $line;
        }

        return $lines;
    }

    private function parsePdf(string $filePath, FinancialOffer $offer): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        $lines = [];

        // Try to extract tabular data from PDF text
        $textLines = explode("\n", $text);
        foreach ($textLines as $textLine) {
            $textLine = trim($textLine);
            if (empty($textLine)) continue;

            // Try to match patterns like: "Resource Name | Type | Unit | Price | Qty"
            $parts = preg_split('/\s{2,}|\t|\|/', $textLine);
            $parts = array_values(array_filter($parts, fn($p) => trim($p) !== ''));

            // Auto-split price and quantity if grouped by a single space
            $newParts = [];
            foreach ($parts as $part) {
                if (preg_match('/^(\d+[\.,]?\d*)\s+(\d+[\.,]?\d*)$/', trim($part), $matches)) {
                    $newParts[] = $matches[1];
                    $newParts[] = $matches[2];
                } else {
                    $newParts[] = $part;
                }
            }
            $parts = $newParts;

            if (count($parts) >= 3) {
                // Check if any part looks like a price (number with decimals)
                $priceIdx = null;
                foreach ($parts as $idx => $part) {
                    if ($idx > 0 && preg_match('/^\d+[\.,]?\d*$/', trim($part))) {
                        $priceIdx = $idx;
                        break;
                    }
                }

                if ($priceIdx !== null && $priceIdx >= 2) {
                    $line = new OfferLine();
                    $line->setResourceName(trim($parts[0]));
                    $line->setServiceType(($priceIdx > 2) ? trim($parts[1]) : null);
                    $line->setUnit(trim($parts[$priceIdx - 1]) ?: 'Unité');
                    $line->setUnitPrice($this->parseNumber($parts[$priceIdx]));
                    if (isset($parts[$priceIdx + 1])) {
                        $line->setQuantity($this->parseNumber($parts[$priceIdx + 1]));
                    }
                    $line->setFinancialOffer($offer);
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    private function parseNumber(string $value): string
    {
        $value = trim($value);
        $value = str_replace([' ', "\xc2\xa0"], '', $value); // Remove spaces and nbsp
        $value = str_replace(',', '.', $value); // French decimal separator
        $value = preg_replace('/[^\d.]/', '', $value);
        return $value ?: '0';
    }
}
