<?php

namespace App\Twig;

use App\Controller\CurrencyController;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CurrencyExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function getGlobals(): array
    {
        $session = $this->requestStack->getSession();
        $code = $session->get('active_currency', 'TND');

        if (!isset(CurrencyController::SUPPORTED_CURRENCIES[$code])) {
            $code = 'TND';
        }

        $currency = CurrencyController::SUPPORTED_CURRENCIES[$code];
        $rate     = CurrencyController::EXCHANGE_RATES[$code];

        return [
            'currency_code'   => $code,
            'currency_symbol' => $currency['symbol'],
            'currency_name'   => $currency['name'],
            'currency_flag'   => $currency['flag'],
            'currency_rate'   => $rate,
            'currencies'      => CurrencyController::SUPPORTED_CURRENCIES,
        ];
    }
}
