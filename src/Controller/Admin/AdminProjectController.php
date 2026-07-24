<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project')]
#[IsGranted('ROLE_ADMIN')]
class AdminProjectController extends AbstractController
{
    #[Route('/', name: 'app_admin_project_index', methods: ['GET'])]
    public function index(ProjectRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('p')
            ->leftJoin('p.company', 'c')->addSelect('c')
            ->orderBy('c.name', 'ASC')->addOrderBy('p.name', 'ASC');

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 20);

        return $this->render('admin/project/index.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/{id}/edit', name: 'app_admin_project_edit', methods: ['GET', 'POST'])]
    public function edit(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Projet mis à jour.');
            return $this->redirectToRoute('app_admin_project_index');
        }

        return $this->render('admin/project/form.html.twig', [
            'form'    => $form,
            'project' => $project,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_project_delete', methods: ['POST'])]
    public function delete(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($project);
            $em->flush();
            $this->addFlash('success', 'Projet supprimé.');
        }
        return $this->redirectToRoute('app_admin_project_index');
    }
}
