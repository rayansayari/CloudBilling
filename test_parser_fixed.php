<?php
require __DIR__ . '/vendor/autoload.php';

use App\Service\FinancialOfferParser;
use App\Entity\FinancialOffer;

$offer = new FinancialOffer();
$parser = new FinancialOfferParser();
$lines = $parser->parse(__DIR__ . '/public/uploads/offre_test.xlsx', $offer);

echo "--- PARSED LINES WITH CORRECTED PARSER ---\n";
echo "Nombre de lignes extraites : " . count($lines) . "\n\n";

foreach ($lines as $idx => $line) {
    echo "Ressource " . ($idx+1) . " : " . $line->getResourceName() . "\n";
    echo "  > Service : " . $line->getServiceType() . "\n";
    echo "  > Unité   : " . $line->getUnit() . "\n";
    echo "  > P.U.    : " . $line->getUnitPrice() . " TND\n";
    echo "  > Qté     : " . $line->getQuantity() . "\n\n";
}
