<?php

namespace App\Controller\Admin;

use App\Repository\InvoiceRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invoice')]
#[IsGranted('ROLE_ADMIN')]
class AdminInvoiceController extends AbstractController
{
    #[Route('/history', name: 'app_admin_invoice_history', methods: ['GET'])]
    public function history(Request $request, InvoiceRepository $invoiceRepository, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $invoiceRepository->createQueryBuilder('i')
            ->orderBy('i.year', 'DESC')
            ->addOrderBy('i.month', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('admin/invoice/history.html.twig', [
            'pagination' => $pagination,
        ]);
    }
}
