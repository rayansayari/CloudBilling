<?php
require __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile(__DIR__ . '/public/uploads/offre_test.pdf');
$text = $pdf->getText();

echo "--- TEXTE EXTRACTED FROM PDF ---\n";
echo $text;
echo "\n--- END TEXT ---\n";

$textLines = explode("\n", $text);
foreach ($textLines as $idx => $textLine) {
    $textLine = trim($textLine);
    if (empty($textLine)) continue;

    // Check splitting
    $parts = preg_split('/\s{2,}|\t|\|/', $textLine);
    $parts = array_values(array_filter($parts, fn($p) => trim($p) !== ''));
    
    echo "Line $idx: " . $textLine . "\n";
    echo "Parts count: " . count($parts) . " -> " . json_encode($parts) . "\n\n";
}
