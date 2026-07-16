<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    #[Route('/', name: 'app_audit_log', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $auditLogRepository, PaginatorInterface $paginator): Response
    {
        $action = $request->query->get('action');
        $entityType = $request->query->get('entity');
        $username = $request->query->get('user');

        $queryBuilder = $auditLogRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC');

        if ($action) {
            $queryBuilder->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityType) {
            $queryBuilder->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($username) {
            $queryBuilder->andWhere('u.email LIKE :username OR u.fullName LIKE :username')
                ->setParameter('username', '%' . $username . '%');
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        // Fetch distinct actions and entities for filters
        $actions = $auditLogRepository->createQueryBuilder('a')->select('DISTINCT a.action')->getQuery()->getSingleColumnResult();
        $entities = $auditLogRepository->createQueryBuilder('a')->select('DISTINCT a.entityType')->getQuery()->getSingleColumnResult();

        return $this->render('audit/index.html.twig', [
            'pagination' => $pagination,
            'actions' => $actions,
            'entities' => $entities,
            'currentAction' => $action,
            'currentEntity' => $entityType,
            'currentUser' => $username,
        ]);
    }
}
