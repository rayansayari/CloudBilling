<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load(__DIR__ . '/public/uploads/offre_test.xlsx');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

echo "--- EXCEL ROWS DETECTED ---\n";
print_r(array_slice($rows, 0, 5));

$headerRow = null;
$columnMap = [];

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
            } elseif (str_contains($cell, 'unité') || str_contains($cell, 'unit')) {
                $columnMap['unit'] = $col;
            } elseif (str_contains($cell, 'prix') || str_contains($cell, 'price') || str_contains($cell, 'tarif') || str_contains($cell, 'pu') || str_contains($cell, 'p.u')) {
                $columnMap['unitPrice'] = $col;
            } elseif (str_contains($cell, 'quantité') || str_contains($cell, 'qty') || str_contains($cell, 'qté')) {
                $columnMap['quantity'] = $col;
            } elseif (str_contains($cell, 'description') || str_contains($cell, 'détail')) {
                $columnMap['description'] = $col;
            }
        }
        break;
    }
}

echo "\nHeader Row: $headerRow\n";
echo "Column Map: " . json_encode($columnMap) . "\n";
