<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// En-têtes du tableau
$sheet->setCellValue('A1', 'Désignation Ressource');
$sheet->setCellValue('B1', 'Type de Service');
$sheet->setCellValue('C1', 'Unité');
$sheet->setCellValue('D1', 'Prix Unitaire (TND)');
$sheet->setCellValue('E1', 'Quantité Estimée');
$sheet->setCellValue('F1', 'Description');

// Données de test (ressources cloud typiques)
$data = [
    ['VM Instance Linux Standard', 'Compute', 'Heure', 0.1250, 730, 'Serveur applicatif principal'],
    ['VM Instance Windows Database', 'Compute', 'Heure', 0.4500, 730, 'Serveur SQL de production (16 Go RAM)'],
    ['Object Storage Cloud S3', 'Storage', 'Go', 0.0800, 1500, 'Stockage sauvegarde fichiers'],
    ['Block Storage SSD NVMe', 'Storage', 'Go', 0.1500, 500, 'Espace disque SSD pour base de données'],
    ['Load Balancer Cloud HA', 'Network', 'Mois', 25.0000, 1, 'Répartiteur de charge applicative'],
    ['Passerelle NAT Gateway', 'Network', 'Heure', 0.0560, 730, 'Sécurisation accès internet privé'],
    ['Trafic Sortant (Egress)', 'Network', 'Go', 0.0900, 100, 'Transfert de données vers internet']
];

foreach ($data as $rowIndex => $rowData) {
    $rowNum = $rowIndex + 2;
    $sheet->setCellValue('A' . $rowNum, $rowData[0]);
    $sheet->setCellValue('B' . $rowNum, $rowData[1]);
    $sheet->setCellValue('C' . $rowNum, $rowData[2]);
    $sheet->setCellValue('D' . $rowNum, $rowData[3]);
    $sheet->setCellValue('E' . $rowNum, $rowData[4]);
    $sheet->setCellValue('F' . $rowNum, $rowData[5]);
}

// Mise en page rapide
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$publicDir = __DIR__ . '/public/uploads';
if (!file_exists($publicDir)) {
    mkdir($publicDir, 0777, true);
}
$writer->save($publicDir . '/offre_test.xlsx');
echo "Fichier d'offre de test créé avec succès.\n";
