<?php

namespace App\Controller\Admin;

use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(
        CompanyRepository  $companyRepository,
        ProjectRepository  $projectRepository,
        InvoiceRepository  $invoiceRepository,
        AuditLogRepository $auditLogRepository
    ): Response {
        $year  = (int) date('Y');
        $month = (int) date('m');

        // KPIs
        $totalCompanies  = count($companyRepository->findAll());
        $activeProjects  = count($projectRepository->findActiveProjects());
        $totalBilled     = $invoiceRepository->getTotalBilledByYear($year);
        $pendingInvoices = count($invoiceRepository->findPendingValidation());

        // Monthly chart data
        $monthlyTotals = $invoiceRepository->getMonthlyTotals($year);
        $chartRevenue  = array_fill(0, 12, 0);
        foreach ($monthlyTotals as $row) {
            $chartRevenue[$row['month'] - 1] = (float) $row['total'];
        }

        // Revenue by company (donut)
        $companies = $companyRepository->findAll();
        $companyNames  = [];
        $companyTotals = [];
        foreach ($companies as $company) {
            $total = 0;
            foreach ($company->getProjects() as $project) {
                foreach ($project->getInvoices() as $invoice) {
                    if ($invoice->isValidated()) {
                        $total += (float) $invoice->getTotalTTC();
                    }
                }
            }
            if ($total > 0) {
                $companyNames[]  = $company->getName();
                $companyTotals[] = $total;
            }
        }

        // Top 5 projects by billed amount
        $allProjects = $projectRepository->findAll();
        $projectStats = [];
        foreach ($allProjects as $project) {
            $total = 0;
            foreach ($project->getInvoices() as $invoice) {
                if ($invoice->isValidated()) {
                    $total += (float) $invoice->getTotalTTC();
                }
            }
            $projectStats[] = ['name' => $project->getSoNumber() . ' - ' . $project->getName(), 'total' => $total];
        }
        usort($projectStats, fn($a, $b) => $b['total'] <=> $a['total']);
        $top5Projects = array_slice($projectStats, 0, 5);

        // Invoice status repartition
        $allInvoices   = $invoiceRepository->findAll();
        $validatedCount = 0;
        $draftCount     = 0;
        foreach ($allInvoices as $inv) {
            $inv->isValidated() ? $validatedCount++ : $draftCount++;
        }

        // Recent audit logs
        $recentLogs = $auditLogRepository->findBy([], ['createdAt' => 'DESC'], 8);

        return $this->render('admin/dashboard.html.twig', [
            'totalCompanies'  => $totalCompanies,
            'activeProjects'  => $activeProjects,
            'totalBilled'     => $totalBilled,
            'pendingInvoices' => $pendingInvoices,
            'chartRevenue'    => $chartRevenue,
            'chartYear'       => $year,
            'companyNames'    => $companyNames,
            'companyTotals'   => $companyTotals,
            'top5Projects'    => $top5Projects,
            'validatedCount'  => $validatedCount,
            'draftCount'      => $draftCount,
            'recentLogs'      => $recentLogs,
        ]);
    }
}
