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
    public function index(
        ProjectRepository $projectRepository,
        InvoiceRepository $invoiceRepository
    ): Response {
        $year = (int) date('Y');

        $activeProjects = count($projectRepository->findActiveProjects());
        $pendingInvoices = count($invoiceRepository->findPendingValidation());
        $totalBilled = $invoiceRepository->getTotalBilledByYear($year);

        // Build monthly chart data as plain PHP array (passed as JSON to template)
        $monthlyTotals = $invoiceRepository->getMonthlyTotals($year);
        $chartData = array_fill(0, 12, 0);
        foreach ($monthlyTotals as $row) {
            $chartData[$row['month'] - 1] = (float) $row['total'];
        }

        return $this->render('dashboard/index.html.twig', [
            'active_projects' => $activeProjects,
            'pending_invoices' => $pendingInvoices,
            'total_billed' => $totalBilled,
            'chart_data' => $chartData,
            'chart_year' => $year,
        ]);
    }
}
