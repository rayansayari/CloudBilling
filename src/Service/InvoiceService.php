<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Project;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepository,
        private AuditService $auditService,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $mailerFromAddress = 'noreply@cloudbill.com',
        private string $mailerFromName = 'CloudBill Platform',
    ) {}

    /**
     * Create or get a monthly invoice for a project.
     * Auto-fills from active financial offer and copies previous month's consumption.
     */
    public function getOrCreateInvoice(Project $project, int $year, int $month): Invoice
    {
        $existing = $this->invoiceRepository->findByProjectAndPeriod($project->getId(), $year, $month);
        if ($existing) {
            return $existing;
        }

        $offer = $project->getActiveOffer();
        if (!$offer) {
            throw new \RuntimeException('Aucune offre financière active pour ce projet.');
        }

        $invoice = new Invoice();
        $invoice->setProject($project);
        $invoice->setFinancialOffer($offer);
        $invoice->setYear($year);
        $invoice->setMonth($month);
        $invoice->setCurrency('TND');
        $invoice->setReference($this->generateReference($project, $year, $month));

        // Get previous month's invoice for consumption carryover
        $previousInvoice = $this->invoiceRepository->findPreviousInvoice($project->getId(), $year, $month);

        // Create invoice lines from offer lines
        foreach ($offer->getOfferLines() as $offerLine) {
            $invoiceLine = new InvoiceLine();
            $invoiceLine->setResourceName($offerLine->getResourceName());
            $invoiceLine->setServiceType($offerLine->getServiceType());
            $invoiceLine->setUnit($offerLine->getUnit());
            $invoiceLine->setUnitPrice($offerLine->getUnitPrice());
            $invoiceLine->setOfferLine($offerLine);

            // Carry over consumption from previous month
            $previousQty = '0';
            if ($previousInvoice) {
                foreach ($previousInvoice->getInvoiceLines() as $prevLine) {
                    if ($prevLine->getOfferLine() && $prevLine->getOfferLine()->getId() === $offerLine->getId()) {
                        $previousQty = $prevLine->getConsumedQuantity();
                        break;
                    }
                }
            }
            $invoiceLine->setConsumedQuantity($previousQty);

            $invoice->addInvoiceLine($invoiceLine);
        }

        $invoice->recalculate();
        $this->em->persist($invoice);
        $this->em->flush();

        $this->auditService->log('CREATE', 'Invoice', $invoice->getId(),
            "Facture {$invoice->getReference()} créée pour {$invoice->getMonthName()} $year");

        return $invoice;
    }

    /**
     * Update consumption quantities and recalculate invoice.
     */
    public function updateConsumptions(Invoice $invoice, array $quantities): void
    {
        if ($invoice->isValidated()) {
            throw new \RuntimeException('Cette facture est déjà validée et ne peut plus être modifiée.');
        }

        foreach ($invoice->getInvoiceLines() as $line) {
            $lineId = $line->getId();
            if (isset($quantities[$lineId])) {
                $line->setConsumedQuantity((string) $quantities[$lineId]);
            }
        }

        $invoice->recalculate();
        $this->em->flush();

        $this->auditService->log('UPDATE', 'Invoice', $invoice->getId(),
            "Consommations mises à jour pour {$invoice->getReference()}");
    }

    /**
     * Validate an invoice (freeze it) and send email notification to the company.
     */
    public function validateInvoice(Invoice $invoice, $user): void
    {
        if ($invoice->isValidated()) {
            throw new \RuntimeException('Cette facture est déjà validée.');
        }

        $invoice->setStatus(Invoice::STATUS_VALIDATED);
        $invoice->setValidatedAt(new \DateTimeImmutable());
        $invoice->setValidatedBy($user);
        $this->em->flush();

        $this->auditService->log('VALIDATE', 'Invoice', $invoice->getId(),
            "Facture {$invoice->getReference()} validée — Total TTC: {$invoice->getTotalTTC()} {$invoice->getCurrency()}");

        // Send email notification to the company
        $this->sendInvoiceValidationEmail($invoice);
    }

    /**
     * Send the validated invoice by email to the company.
     */
    private function sendInvoiceValidationEmail(Invoice $invoice): void
    {
        $company = $invoice->getProject()?->getCompany();
        $companyEmail = $company?->getEmail();

        if (!$companyEmail) {
            // No email configured for this company — skip silently
            return;
        }

        try {
            $htmlContent = $this->twig->render('emails/invoice_validated.html.twig', [
                'invoice' => $invoice,
                'company' => $company,
            ]);

            $email = (new Email())
                ->from("{$this->mailerFromName} <{$this->mailerFromAddress}>")
                ->to($companyEmail)
                ->subject("Facture {$invoice->getReference()} — {$invoice->getMonthName()} {$invoice->getYear()} — CloudBill")
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->auditService->log('EMAIL', 'Invoice', $invoice->getId(),
                "Email de facturation envoyé à {$companyEmail} pour {$invoice->getReference()}");
        } catch (\Exception $e) {
            // Log error but don't fail the validation process
            $this->auditService->log('EMAIL_ERROR', 'Invoice', $invoice->getId(),
                "Échec envoi email à {$companyEmail} : {$e->getMessage()}");
        }
    }

    /**
     * Unlock invoice (admin only).
     */
    public function unlockInvoice(Invoice $invoice): void
    {
        $invoice->setStatus(Invoice::STATUS_DRAFT);
        $invoice->setValidatedAt(null);
        $invoice->setValidatedBy(null);
        $this->em->flush();

        $this->auditService->log('UNLOCK', 'Invoice', $invoice->getId(),
            "Facture {$invoice->getReference()} déverrouillée par un administrateur");
    }

    /**
     * Generate consolidated invoice data for all projects of a company in a period.
     */
    public function getConsolidatedData(\App\Entity\Company $company, int $year, int $month): array
    {
        $data = [];
        foreach ($company->getProjects() as $project) {
            $invoice = $this->invoiceRepository->findByProjectAndPeriod($project->getId(), $year, $month);
            $data[] = [
                'project' => $project,
                'invoice' => $invoice
            ];
        }
        return $data;
    }

    private function generateReference(Project $project, int $year, int $month): string
    {
        return sprintf('FACT-%s-%04d%02d', $project->getSoNumber(), $year, $month);
    }
}
