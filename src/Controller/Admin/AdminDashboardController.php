<?php

namespace App\Controller\Admin;

use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\AuditLogRepository;
use App\Service\FinancialForecastingService;
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
        CompanyRepository         $companyRepository,
        ProjectRepository         $projectRepository,
        InvoiceRepository         $invoiceRepository,
        AuditLogRepository        $auditLogRepository,
        FinancialForecastingService $forecastingService
    ): Response {
        $year  = (int) date('Y');
        $month = (int) date('m');

        // KPIs
        $totalCompanies  = count($companyRepository->findAll());
        $activeProjects  = count($projectRepository->findActiveProjects());
        $totalBilled     = $invoiceRepository->getTotalBilledByYear($year);
        $pendingInvoices = count($invoiceRepository->findPendingValidation());

        // Monthly chart data (Revenue TTC)
        $monthlyTotals = $invoiceRepository->getMonthlyTotals($year);
        $chartRevenue  = array_fill(0, 12, 0);
        foreach ($monthlyTotals as $row) {
            $chartRevenue[$row['month'] - 1] = (float) $row['total'];
        }

        // Run Machine Learning Time-Series Forecast (6 months horizon)
        $mlForecast = $forecastingService->forecastRevenue($chartRevenue, 6);


        // Monthly Breakdown HT vs TTC (Line chart)
        $monthlyBreakdown = $invoiceRepository->getMonthlyBreakdownHTvsTTC($year);
        $chartHT  = array_fill(0, 12, 0);
        $chartTTC = array_fill(0, 12, 0);
        foreach ($monthlyBreakdown as $row) {
            $chartHT[$row['month'] - 1]  = (float) $row['totalHT'];
            $chartTTC[$row['month'] - 1] = (float) $row['totalTTC'];
        }

        // Monthly Invoice Volume by Status (Line chart)
        $monthlyStatusCounts = $invoiceRepository->getMonthlyInvoiceCountByStatus($year);
        $chartValidatedCount = array_fill(0, 12, 0);
        $chartDraftCount     = array_fill(0, 12, 0);
        foreach ($monthlyStatusCounts as $row) {
            $m = $row['month'] - 1;
            if ($row['status'] === \App\Entity\Invoice::STATUS_VALIDATED) {
                $chartValidatedCount[$m] = (int) $row['count'];
            } else {
                $chartDraftCount[$m]     = (int) $row['count'];
            }
        }

        // Monthly Project Creation Trend (Line chart)
        $allProjects = $projectRepository->findAll();
        $chartProjectsCreated = array_fill(0, 12, 0);
        foreach ($allProjects as $proj) {
            $m = (int) $proj->getCreatedAt()->format('n') - 1;
            $chartProjectsCreated[$m]++;
        }

        // Monthly Financial Candlestick Data (OHLC)
        $candlestickData = [];
        for ($m = 0; $m < 12; $m++) {
            $ht    = $chartHT[$m];
            $ttc   = $chartTTC[$m];
            $open  = $ht > 0 ? $ht : ($m > 0 && isset($candlestickData[$m - 1]) ? $candlestickData[$m - 1]['close'] : 0);
            $close = $ttc;

            // Generate realistic High & Low wicks for billing variations
            $high  = max($open, $close) * ($open > 0 || $close > 0 ? 1.08 : 0);
            $low   = min($open, $close) * ($open > 0 || $close > 0 ? 0.92 : 0);

            $candlestickData[] = [
                'open'  => round($open, 2),
                'high'  => round($high, 2),
                'low'   => round($low, 2),
                'close' => round($close, 2),
            ];
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

        // Projects status repartition
        $activeProjCount = 0;
        $pausedProjCount = 0;
        $completedProjCount = 0;
        foreach ($allProjects as $project) {
            $status = $project->getStatus();
            if ($status === 'Actif') {
                $activeProjCount++;
            } elseif ($status === 'En pause') {
                $pausedProjCount++;
            } else {
                $completedProjCount++;
            }
        }

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
            'totalCompanies'       => $totalCompanies,
            'activeProjects'       => $activeProjects,
            'totalBilled'          => $totalBilled,
            'pendingInvoices'      => $pendingInvoices,
            'chartRevenue'         => $chartRevenue,
            'chartHT'              => $chartHT,
            'chartTTC'             => $chartTTC,
            'chartValidatedCount'  => $chartValidatedCount,
            'chartDraftCount'      => $chartDraftCount,
            'chartProjectsCreated' => $chartProjectsCreated,
            'candlestickData'      => $candlestickData,
            'mlForecast'           => $mlForecast,
            'chartYear'            => $year,
            'companyNames'         => $companyNames,
            'companyTotals'        => $companyTotals,
            'top5Projects'         => $top5Projects,
            'validatedCount'       => $validatedCount,
            'draftCount'           => $draftCount,
            'activeProjCount'      => $activeProjCount,
            'pausedProjCount'      => $pausedProjCount,
            'completedProjCount'   => $completedProjCount,
            'recentLogs'           => $recentLogs,
        ]);
    }

}
