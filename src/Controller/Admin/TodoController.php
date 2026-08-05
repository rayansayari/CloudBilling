<?php

namespace App\Controller\Admin;

use App\Entity\TodoTask;
use App\Repository\TodoTaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/todo')]
#[IsGranted('ROLE_ADMIN')]
class TodoController extends AbstractController
{
    /**
     * Main Kanban board — Admin view (all tasks, filterable by assignee).
     */
    #[Route('', name: 'app_admin_todo', methods: ['GET'])]
    public function index(
        Request             $request,
        TodoTaskRepository  $todoRepo,
        UserRepository      $userRepo
    ): Response {
        $filterUserId = $request->query->getInt('user', 0);
        $filterUser   = $filterUserId > 0 ? $userRepo->find($filterUserId) : null;

        $grouped   = $todoRepo->findAllGroupedByStatus($filterUser);
        $allUsers  = $userRepo->findAll();

        return $this->render('admin/todo/index.html.twig', [
            'todoTasks'    => $grouped,
            'allUsers'     => $allUsers,
            'filterUserId' => $filterUserId,
        ]);
    }

    /**
     * Create a new task and assign it to a user.
     */
    #[Route('/create', name: 'app_admin_todo_create', methods: ['POST'])]
    public function create(
        Request             $request,
        EntityManagerInterface $em,
        UserRepository      $userRepo,
        TodoTaskRepository  $todoRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title'])) {
            return $this->json(['error' => 'Le titre est obligatoire.'], 400);
        }

        $task = new TodoTask();
        $task->setTitle(trim($data['title']));
        $task->setDescription($data['description'] ?? null);
        $task->setStatus($data['status'] ?? TodoTask::STATUS_TODO);
        $task->setPriority($data['priority'] ?? TodoTask::PRIORITY_MEDIUM);
        $task->setCreatedBy($this->getUser());

        if (!empty($data['assignedToId'])) {
            $assignee = $userRepo->find((int) $data['assignedToId']);
            $task->setAssignedTo($assignee);
        }

        if (!empty($data['dueDate'])) {
            $task->setDueDate(new \DateTime($data['dueDate']));
        }

        // Append to the end of the chosen column
        $maxPos = $todoRepo->getMaxPositionForStatus($task->getStatus());
        $task->setPosition($maxPos + 1);

        $em->persist($task);
        $em->flush();

        return $this->json($this->taskToArray($task), 201);
    }

    /**
     * Move a task to another column / reorder within the same column.
     */
    #[Route('/{id}/move', name: 'app_admin_todo_move', methods: ['PATCH'])]
    public function move(
        TodoTask $task,
        Request  $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['status'])) {
            $validStatuses = [TodoTask::STATUS_TODO, TodoTask::STATUS_IN_PROGRESS, TodoTask::STATUS_DONE];
            if (!in_array($data['status'], $validStatuses, true)) {
                return $this->json(['error' => 'Statut invalide.'], 400);
            }
            $task->setStatus($data['status']);
        }

        if (isset($data['position'])) {
            $task->setPosition((int) $data['position']);
        }

        $em->flush();

        return $this->json(['ok' => true, 'status' => $task->getStatus(), 'position' => $task->getPosition()]);
    }

    /**
     * Edit task fields (title, description, priority, dueDate, assignedTo).
     */
    #[Route('/{id}/edit', name: 'app_admin_todo_edit', methods: ['PATCH'])]
    public function edit(
        TodoTask $task,
        Request  $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['title']) && trim($data['title']) !== '') {
            $task->setTitle(trim($data['title']));
        }

        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description'] ?: null);
        }

        if (isset($data['priority'])) {
            $task->setPriority($data['priority']);
        }

        if (array_key_exists('dueDate', $data)) {
            $task->setDueDate($data['dueDate'] ? new \DateTime($data['dueDate']) : null);
        }

        if (array_key_exists('assignedToId', $data)) {
            $assignee = $data['assignedToId'] ? $userRepo->find((int) $data['assignedToId']) : null;
            $task->setAssignedTo($assignee);
        }

        $em->flush();

        return $this->json($this->taskToArray($task));
    }

    /**
     * Delete a task.
     */
    #[Route('/{id}/delete', name: 'app_admin_todo_delete', methods: ['DELETE'])]
    public function delete(
        TodoTask $task,
        EntityManagerInterface $em
    ): JsonResponse {
        $em->remove($task);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    // ─── Helper ────────────────────────────────────────────────────────────

    private function taskToArray(TodoTask $task): array
    {
        return [
            'id'           => $task->getId(),
            'title'        => $task->getTitle(),
            'description'  => $task->getDescription(),
            'status'       => $task->getStatus(),
            'priority'     => $task->getPriority(),
            'position'     => $task->getPosition(),
            'dueDate'      => $task->getDueDate()?->format('Y-m-d'),
            'isOverdue'    => $task->isOverdue(),
            'assignedTo'   => $task->getAssignedTo() ? [
                'id'       => $task->getAssignedTo()->getId(),
                'fullName' => $task->getAssignedTo()->getFullName(),
                'email'    => $task->getAssignedTo()->getEmail(),
            ] : null,
            'createdBy'    => $task->getCreatedBy()?->getFullName(),
            'createdAt'    => $task->getCreatedAt()?->format('d/m/Y'),
        ];
    }
}
