<?php

namespace App\Service;

use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\TodoTaskRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * SmartNotificationService
 * 
 * Aggregates real-time business alerts across Invoices, To-Do Tasks, and Projects.
 */
class SmartNotificationService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private TodoTaskRepository $todoTaskRepository,
        private ProjectRepository $projectRepository,
        private UrlGeneratorInterface $router
    ) {}

    /**
     * Get all active smart notifications for the platform.
     *
     * @return array{
     *     totalCount: int,
     *     dangerCount: int,
     *     warningCount: int,
     *     infoCount: int,
     *     items: array<array{
     *         id: string,
     *         type: string, // 'danger' | 'warning' | 'info'
     *         title: string,
     *         message: string,
     *         date: string,
     *         icon: string,
     *         url: string
     *     }>
     * }
     */
    public function getNotifications(?int $userId = null): array
    {
        $items = [];
        $today = new \DateTime();

        // 🔴 1. Factures impayées / en attente de validation (Danger/Critical)
        $draftInvoices = $this->invoiceRepository->findBy(['status' => 'Brouillon'], ['createdAt' => 'DESC'], 5);
        foreach ($draftInvoices as $inv) {
            $ref = method_exists($inv, 'getReference') ? $inv->getReference() : ('FAC-#' . $inv->getId());
            $items[] = [
                'id'      => 'inv-' . $inv->getId(),
                'type'    => 'danger',
                'title'   => 'Facture à Valider',
                'message' => sprintf('Facture %s (%s TND) en attente de validation', $ref, number_format((float)$inv->getTotalTtc(), 2, ',', ' ')),
                'date'    => $inv->getCreatedAt()?->format('d/m/Y') ?? 'Récemment',
                'icon'    => 'fas fa-file-invoice-dollar',
                'url'     => $this->router->generate('app_admin_invoice_history')
            ];
        }


        // 🟡 2. Tâches To-Do en retard (Warning)
        $overdueTasks = $this->todoTaskRepository->createQueryBuilder('t')
            ->where('t.status != :doneStatus')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate < :today')
            ->setParameter('doneStatus', 'done')
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('t.dueDate', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($overdueTasks as $task) {
            // If filtering for consultant
            if ($userId && $task->getAssignedTo()?->getId() !== $userId) {
                continue;
            }
            $items[] = [
                'id'      => 'todo-' . $task->getId(),
                'type'    => 'warning',
                'title'   => 'Tâche en Retard',
                'message' => sprintf('Tâche "%s" dépassée depuis le %s', $task->getTitle(), $task->getDueDate()?->format('d/m/Y')),
                'date'    => $task->getDueDate()?->format('d/m/Y') ?? 'Aujourd\'hui',
                'icon'    => 'fas fa-clock',
                'url'     => $this->router->generate('app_admin_todo')
            ];
        }

        // 🟢 3. Nouveaux Projets / Projets actifs récemment créés (Info)
        $recentProjects = $this->projectRepository->findBy(['status' => 'Actif'], ['createdAt' => 'DESC'], 5);
        foreach ($recentProjects as $proj) {
            $items[] = [
                'id'      => 'proj-' . $proj->getId(),
                'type'    => 'info',
                'title'   => 'Nouveau Projet Actif',
                'message' => sprintf('Projet SO "%s" (%s)', $proj->getName(), $proj->getCompany()?->getName() ?? 'Client'),
                'date'    => $proj->getCreatedAt()?->format('d/m/Y') ?? 'Actif',
                'icon'    => 'fas fa-diagram-project',
                'url'     => $this->router->generate('app_admin_project_index')
            ];
        }

        // Count totals
        $dangerCount  = count(array_filter($items, fn($i) => $i['type'] === 'danger'));
        $warningCount = count(array_filter($items, fn($i) => $i['type'] === 'warning'));
        $infoCount    = count(array_filter($items, fn($i) => $i['type'] === 'info'));

        return [
            'totalCount'   => count($items),
            'dangerCount'  => $dangerCount,
            'warningCount' => $warningCount,
            'infoCount'    => $infoCount,
            'items'        => array_slice($items, 0, 10)
        ];
    }
}
