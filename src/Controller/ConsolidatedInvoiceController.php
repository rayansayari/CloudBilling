<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Service\InvoiceExportService;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consolidated')]
class ConsolidatedInvoiceController extends AbstractController
{
    #[Route('/', name: 'app_consolidated_index', methods: ['GET'])]
    public function index(Request $request, CompanyRepository $companyRepository, InvoiceService $invoiceService): Response
    {
        $companies = $companyRepository->findAll();
        
        $selectedCompanyId = $request->query->get('company');
        $selectedYear = $request->query->getInt('year', (int) date('Y'));
        $selectedMonth = $request->query->getInt('month', (int) date('m'));
        
        $consolidatedData = [];
        $selectedCompany = null;
        $grandTotalHT = 0;
        $grandTotalTVA = 0;
        $grandTotalTTC = 0;

        if ($selectedCompanyId) {
            $selectedCompany = $companyRepository->find($selectedCompanyId);
            if ($selectedCompany) {
                $consolidatedData = $invoiceService->getConsolidatedData($selectedCompany, $selectedYear, $selectedMonth);
                
                foreach ($consolidatedData as $data) {
                    $invoice = $data['invoice'];
                    if ($invoice) {
                        $grandTotalHT += $invoice->getTotalHT();
                        $grandTotalTVA += $invoice->getTotalTVA();
                        $grandTotalTTC += $invoice->getTotalTTC();
                    }
                }
            }
        }

        return $this->render('consolidated/index.html.twig', [
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'consolidatedData' => $consolidatedData,
            'grandTotalHT' => $grandTotalHT,
            'grandTotalTVA' => $grandTotalTVA,
            'grandTotalTTC' => $grandTotalTTC,
            'months' => [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ]
        ]);
    }

    #[Route('/export/pdf', name: 'app_consolidated_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, CompanyRepository $companyRepository, InvoiceService $invoiceService, InvoiceExportService $exportService): Response
    {
        $companyId = $request->query->get('company');
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('m'));
        
        if (!$companyId) {
            $this->addFlash('danger', 'Veuillez sélectionner une société.');
            return $this->redirectToRoute('app_consolidated_index');
        }
        
        $company = $companyRepository->find($companyId);
        if (!$company) {
            throw $this->createNotFoundException('Société non trouvée.');
        }

        $consolidatedData = $invoiceService->getConsolidatedData($company, $year, $month);
        
        $grandTotalHT = 0;
        $grandTotalTVA = 0;
        $grandTotalTTC = 0;

        foreach ($consolidatedData as $data) {
            $invoice = $data['invoice'];
            if ($invoice) {
                $grandTotalHT += $invoice->getTotalHT();
                $grandTotalTVA += $invoice->getTotalTVA();
                $grandTotalTTC += $invoice->getTotalTTC();
            }
        }

        $pdfContent = $exportService->generateConsolidatedPdf($company, $year, $month, $consolidatedData, $grandTotalHT, $grandTotalTVA, $grandTotalTTC);
        
        $filename = sprintf('facture_consolidee_%s_%04d_%02d.pdf', preg_replace('/[^a-zA-Z0-9]/', '_', $company->getName()), $year, $month);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }
}
