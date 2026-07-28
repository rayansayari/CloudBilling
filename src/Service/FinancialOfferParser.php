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

        // Auto-detect header row — extended keywords
        foreach ($rows as $rowIndex => $row) {
            $rowStr = strtolower(implode(' ', array_filter($row)));
            if (str_contains($rowStr, 'ressource') || str_contains($rowStr, 'resource') ||
                str_contains($rowStr, 'service')   || str_contains($rowStr, 'désignation') ||
                str_contains($rowStr, 'nom du service') || str_contains($rowStr, 'description') ||
                str_contains($rowStr, 'sku')) {
                $headerRow = $rowIndex;
                foreach ($row as $col => $cell) {
                    $cell = strtolower(trim((string)($cell ?? '')));
                    if (str_contains($cell, 'ressource') || str_contains($cell, 'resource') || str_contains($cell, 'désignation') || str_contains($cell, 'nom du service') || str_contains($cell, 'nom service')) {
                        $columnMap['resource'] = $col;
                    } elseif (str_contains($cell, 'sku')) {
                        $columnMap['sku'] = $col;
                    } elseif (str_contains($cell, 'catégorie') || str_contains($cell, 'categorie') || ($cell === 'type') || str_contains($cell, 'service cloud') || str_contains($cell, 'cloud')) {
                        $columnMap['serviceType'] = $col;
                    } elseif (str_contains($cell, 'terme') || $cell === 'term' || str_contains($cell, 'durée') || str_contains($cell, 'mois')) {
                        $columnMap['term'] = $col;
                    } elseif (str_contains($cell, 'unité') || $cell === 'unit' || str_contains($cell, 'uom')) {
                        $columnMap['unit'] = $col;
                    } elseif (str_contains($cell, 'quantité') || str_contains($cell, 'qty') || str_contains($cell, 'qté') || str_contains($cell, 'quantite')) {
                        $columnMap['quantity'] = $col;
                    } elseif (str_contains($cell, 'prix unit') || str_contains($cell, 'pu') || str_contains($cell, 'p.u') || str_contains($cell, 'unit price') || str_contains($cell, 'tarif')) {
                        $columnMap['unitPrice'] = $col;
                    } elseif (str_contains($cell, 'remise') || str_contains($cell, 'discount') || str_contains($cell, 'réduction') || str_contains($cell, 'reduction')) {
                        $columnMap['discount'] = $col;
                    } elseif (str_contains($cell, 'type remise') || str_contains($cell, 'type discount') || str_contains($cell, 'discount type')) {
                        $columnMap['discountType'] = $col;
                    } elseif (str_contains($cell, 'description') || str_contains($cell, 'détail') || str_contains($cell, 'detail')) {
                        $columnMap['description'] = $col;
                    }
                }
                break;
            }
        }

        // Fallback: assume first row is header
        if (!$headerRow) {
            $headerRow = array_key_first($rows) ?? 1;
            $firstRow = $rows[$headerRow];
            $cols = array_keys($firstRow);
            $columnMap = [
                'resource'    => $cols[0] ?? 'A',
                'serviceType' => $cols[1] ?? 'B',
                'sku'         => $cols[2] ?? 'C',
                'term'        => $cols[3] ?? 'D',
                'unit'        => $cols[4] ?? 'E',
                'quantity'    => $cols[5] ?? 'F',
                'unitPrice'   => $cols[6] ?? 'G',
                'discount'    => $cols[7] ?? 'H',
            ];
        }

        // Parse data rows
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex <= $headerRow) continue;

            $resourceName = trim((string)($row[$columnMap['resource'] ?? 'A'] ?? ''));
            if (empty($resourceName)) continue;

            $unitPrice = $this->parseNumber((string)($row[$columnMap['unitPrice'] ?? 'G'] ?? '0'));
            if ((float)$unitPrice === 0.0) continue;

            $discountRaw  = trim((string)($row[$columnMap['discount'] ?? ''] ?? ''));
            $discountType = trim(strtolower((string)($row[$columnMap['discountType'] ?? ''] ?? '')));

            // Auto-detect discount type: if value ends with '%' => percent
            if (str_ends_with($discountRaw, '%')) {
                $discountRaw  = rtrim($discountRaw, '%');
                $discountType = 'percent';
            } elseif ($discountType === '' && is_numeric($this->parseNumber($discountRaw)) && (float)$this->parseNumber($discountRaw) > 0) {
                // Default to 'fixed' if no type given but there is a value
                $discountType = 'fixed';
            }

            $termRaw = trim((string)($row[$columnMap['term'] ?? ''] ?? '1'));
            $term = max(1, (int)$this->parseNumber($termRaw ?: '1'));

            $line = new OfferLine();
            $line->setResourceName($resourceName);
            $line->setServiceType(trim((string)($row[$columnMap['serviceType'] ?? 'B'] ?? '')) ?: null);
            $line->setSkuId(trim((string)($row[$columnMap['sku'] ?? ''] ?? '')) ?: null);
            $line->setTerm($term);
            $line->setUnit(trim((string)($row[$columnMap['unit'] ?? 'E'] ?? '')) ?: 'Unité');
            $line->setUnitPrice($unitPrice);
            $line->setQuantity($this->parseNumber((string)($row[$columnMap['quantity'] ?? 'F'] ?? '1')) ?: '1');
            $line->setDiscount($discountRaw !== '' ? $this->parseNumber($discountRaw) : null);
            $line->setDiscountType($discountType !== '' ? $discountType : null);
            $line->setDescription(trim((string)($row[$columnMap['description'] ?? ''] ?? '')) ?: null);
            $line->setFinancialOffer($offer);

            $lines[] = $line;
        }

        return $lines;
    }

    private function parsePdf(string $filePath, FinancialOffer $offer): array
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();
        $lines  = [];

        $textLines = explode("\n", $text);
        foreach ($textLines as $textLine) {
            $textLine = trim(preg_replace('/\s+/', ' ', $textLine));
            if (empty($textLine)) continue;

            // Pattern for standard NetSolutions format:
            // [Category] [SKU] [Name...] [Term] [Qty] [Unit] [Price] [Discount?] [Type?] [SubTotal] [Total]
            // Example: IaaS IAAS-001 VM Linux Standard 12 3 VM 120 10% percent 4320 3888
            $pattern = '/^(IaaS|PaaS|SaaS|Storage|Backup|Database|Network|Security|Recovery)\s+([A-Z0-9-]+)\s+(.+?)\s+(\d+)\s+(\d+)\s+([\w\s]+?)\s+([\d.,]+)\s*(?:([\d.,]+%?|[\d.,]+\s*TND)\s*(percent|fixed))?\s*([\d.,]+)\s+([\d.,]+)$/i';
            
            if (preg_match($pattern, $textLine, $matches)) {
                $line = new OfferLine();
                $line->setServiceType($matches[1]);
                $line->setSkuId($matches[2]);
                $line->setResourceName(trim($matches[3]));
                $line->setTerm((int) $matches[4]);
                $line->setQuantity($this->parseNumber($matches[5]));
                $line->setUnit(trim($matches[6]));
                $line->setUnitPrice($this->parseNumber($matches[7]));
                
                if (!empty($matches[8])) {
                    $discountStr = str_replace(['%', 'TND', ' '], '', $matches[8]);
                    $line->setDiscount($this->parseNumber($discountStr));
                    $line->setDiscountType(strtolower($matches[9] ?? ''));
                }
                
                $line->setFinancialOffer($offer);
                $lines[] = $line;
                continue;
            }

            // Fallback: Generic heuristic parsing for simpler formats
            $parts = preg_split('/\s{2,}|\t|\|/', $textLine);
            $parts = array_values(array_filter($parts, fn($p) => trim($p) !== ''));

            if (count($parts) >= 3) {
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
                    $line->setQuantity(isset($parts[$priceIdx + 1]) ? $this->parseNumber($parts[$priceIdx + 1]) : '1');
                    $line->setTerm(1);
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
        $value = str_replace([' ', "\xc2\xa0"], '', $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^\d.]/', '', $value);
        return $value ?: '0';
    }
}
