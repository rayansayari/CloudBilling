<?php

namespace App\Controller;

use App\Entity\TodoTask;
use App\Repository\TodoTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/todo')]
#[IsGranted('ROLE_USER')]
class TodoConsultantController extends AbstractController
{
    /**
     * Kanban board — Consultant view (only his/her assigned tasks).
     */
    #[Route('', name: 'app_todo', methods: ['GET'])]
    public function index(TodoTaskRepository $todoRepo): Response
    {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $grouped = $todoRepo->findByAssigneeGroupedByStatus($user);

        return $this->render('todo/index.html.twig', [
            'todoTasks' => $grouped,
        ]);
    }

    /**
     * Consultant can move their own tasks between columns.
     */
    #[Route('/{id}/move', name: 'app_todo_move', methods: ['PATCH'])]
    public function move(
        TodoTask $task,
        Request  $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Security: consultant can only move tasks assigned to himself
        if ($task->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $validStatuses = [TodoTask::STATUS_TODO, TodoTask::STATUS_IN_PROGRESS, TodoTask::STATUS_DONE];
        if (isset($data['status']) && in_array($data['status'], $validStatuses, true)) {
            $task->setStatus($data['status']);
        }

        if (isset($data['position'])) {
            $task->setPosition((int) $data['position']);
        }

        $em->flush();

        return $this->json(['ok' => true, 'status' => $task->getStatus()]);
    }
}
