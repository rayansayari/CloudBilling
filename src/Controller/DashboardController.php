<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response 
    {
        // Si l'utilisateur est un Administrateur, on l'envoie sur le nouveau Back Office
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Sinon (Consultant), on l'envoie sur la vue des Sociétés (Front Office)
        return $this->redirectToRoute('app_company_index');
    }
}
