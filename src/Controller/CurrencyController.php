<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class CurrencyController extends AbstractController
{
    public const SUPPORTED_CURRENCIES = [
        'TND' => ['symbol' => 'د.ت', 'name' => 'Dinar Tunisien', 'flag' => '🇹🇳'],
        'EUR' => ['symbol' => '€',    'name' => 'Euro',           'flag' => '🇪🇺'],
        'USD' => ['symbol' => '$',    'name' => 'Dollar US',      'flag' => '🇺🇸'],
        'GBP' => ['symbol' => '£',    'name' => 'Livre Sterling', 'flag' => '🇬🇧'],
    ];

    // Exchange rates relative to TND (base currency)
    public const EXCHANGE_RATES = [
        'TND' => 1.0,
        'EUR' => 0.297,   // 1 TND ≈ 0.297 EUR (1 EUR ≈ 3.367 TND)
        'USD' => 0.322,   // 1 TND ≈ 0.322 USD (1 USD ≈ 3.106 TND)
        'GBP' => 0.251,   // 1 TND ≈ 0.251 GBP (1 GBP ≈ 3.984 TND)
    ];

    #[Route('/currency/switch/{code}', name: 'app_currency_switch')]
    public function switch(string $code, Request $request, SessionInterface $session): RedirectResponse
    {
        if (isset(self::SUPPORTED_CURRENCIES[$code])) {
            $session->set('active_currency', $code);
        }

        $referer = $request->headers->get('referer', '/');
        return $this->redirect($referer);
    }
}
