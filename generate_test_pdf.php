<?php
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Offre Financière Test</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #333; }
        h1 { color: #6366f1; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>Offre Financière - Cloud Ressources</h1>
    <p><strong>Référence :</strong> OFF-2026-TEST</p>
    <p><strong>Date d\'effet :</strong> 15/07/2026</p>
    
    <table>
        <thead>
            <tr>
                <th>Ressource</th>
                <th>Type de Service</th>
                <th>Unité</th>
                <th class="text-right">Prix Unitaire</th>
                <th class="text-right">Quantité</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>VM Instance Linux Standard</td>
                <td>Compute</td>
                <td>Heure</td>
                <td class="text-right">0.1250</td>
                <td class="text-right">730</td>
            </tr>
            <tr>
                <td>VM Instance Windows Database</td>
                <td>Compute</td>
                <td>Heure</td>
                <td class="text-right">0.4500</td>
                <td class="text-right">730</td>
            </tr>
            <tr>
                <td>Object Storage Cloud S3</td>
                <td>Storage</td>
                <td>Go</td>
                <td class="text-right">0.0800</td>
                <td class="text-right">1500</td>
            </tr>
            <tr>
                <td>Block Storage SSD NVMe</td>
                <td>Storage</td>
                <td>Go</td>
                <td class="text-right">0.1500</td>
                <td class="text-right">500</td>
            </tr>
            <tr>
                <td>Load Balancer Cloud HA</td>
                <td>Network</td>
                <td>Mois</td>
                <td class="text-right">25.0000</td>
                <td class="text-right">1</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$publicDir = __DIR__ . '/public/uploads';
if (!file_exists($publicDir)) {
    mkdir($publicDir, 0777, true);
}
file_put_contents($publicDir . '/offre_test.pdf', $dompdf->output());
echo "Fichier PDF de test créé avec succès.\n";
