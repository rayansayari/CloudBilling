<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceHistoryController extends AbstractController
{
    #[Route('/invoices', name: 'app_invoice_history', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $invoiceRepository->createQueryBuilder('i')
            ->orderBy('i.year', 'DESC')
            ->addOrderBy('i.month', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('invoice/history.html.twig', [
            'pagination' => $pagination,
        ]);
    }
}
