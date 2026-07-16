<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Service\InvoiceExportService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project/{project_id}/invoice')]
class InvoiceController extends AbstractController
{
    #[Route('/generate', name: 'app_invoice_generate', methods: ['GET', 'POST'])]
    public function generate(
        int $project_id, 
        Request $request, 
        EntityManagerInterface $em,
        InvoiceService $invoiceService
    ): Response {
        $project = $em->getRepository(Project::class)->find($project_id);
        
        $year = $request->query->getInt('year', (int)date('Y'));
        $month = $request->query->getInt('month', (int)date('m'));

        if (!$project->getActiveOffer()) {
            $this->addFlash('warning', 'Vous devez d\'abord importer et activer une offre financière pour ce projet.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        try {
            $invoice = $invoiceService->getOrCreateInvoice($project, $year, $month);
            return $this->redirectToRoute('app_invoice_edit', ['project_id' => $project->getId(), 'id' => $invoice->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $project_id, 
        Invoice $invoice, 
        Request $request,
        InvoiceService $invoiceService
    ): Response {
        if ($invoice->getProject()->getId() !== $project_id) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if ($invoice->isValidated()) {
                $this->addFlash('danger', 'La facture est validée et ne peut plus être modifiée.');
                return $this->redirectToRoute('app_invoice_edit', ['project_id' => $project_id, 'id' => $invoice->getId()]);
            }

            $quantities = $request->request->all('quantities');
            
            try {
                $invoiceService->updateConsumptions($invoice, $quantities);
                
                if ($request->request->has('validate')) {
                    $invoiceService->validateInvoice($invoice, $this->getUser());
                    $this->addFlash('success', 'La facture a été enregistrée et validée définitivement.');
                } else {
                    $this->addFlash('success', 'Les consommations ont été enregistrées (Brouillon).');
                }
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_invoice_edit', ['project_id' => $project_id, 'id' => $invoice->getId()]);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
            'project' => $invoice->getProject()
        ]);
    }

    #[Route('/{id}/export/pdf', name: 'app_invoice_export_pdf', methods: ['GET'])]
    public function exportPdf(int $project_id, Invoice $invoice, InvoiceExportService $exportService): Response
    {
        if ($invoice->getProject()->getId() !== $project_id) {
            throw $this->createNotFoundException();
        }

        $pdfContent = $exportService->exportPdf($invoice);
        
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->getReference().'.pdf"'
        ]);
    }

    #[Route('/{id}/export/excel', name: 'app_invoice_export_excel', methods: ['GET'])]
    public function exportExcel(int $project_id, Invoice $invoice, InvoiceExportService $exportService): Response
    {
        if ($invoice->getProject()->getId() !== $project_id) {
            throw $this->createNotFoundException();
        }

        $excelContent = $exportService->exportExcel($invoice);
        
        return new Response($excelContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$invoice->getReference().'.xlsx"'
        ]);
    }
}
