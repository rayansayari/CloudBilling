<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/company')]
#[IsGranted('ROLE_ADMIN')]
class AdminCompanyController extends AbstractController
{
    #[Route('/', name: 'app_admin_company_index', methods: ['GET'])]
    public function index(CompanyRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('c')->orderBy('c.name', 'ASC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        return $this->render('admin/company/index.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/new', name: 'app_admin_company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($company);
            $em->flush();
            $this->addFlash('success', 'Société créée avec succès.');
            return $this->redirectToRoute('app_admin_company_index');
        }

        return $this->render('admin/company/form.html.twig', [
            'form'    => $form,
            'company' => $company,
            'title'   => 'Nouvelle Société',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_company_edit', methods: ['GET', 'POST'])]
    public function edit(Company $company, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Société mise à jour.');
            return $this->redirectToRoute('app_admin_company_index');
        }

        return $this->render('admin/company/form.html.twig', [
            'form'    => $form,
            'company' => $company,
            'title'   => 'Modifier : ' . $company->getName(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_company_delete', methods: ['POST'])]
    public function delete(Company $company, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $company->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($company);
            $em->flush();
            $this->addFlash('success', 'Société supprimée.');
        }
        return $this->redirectToRoute('app_admin_company_index');
    }
}
