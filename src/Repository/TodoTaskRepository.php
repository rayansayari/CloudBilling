<?php

namespace App\Repository;

use App\Entity\TodoTask;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TodoTask> */
class TodoTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TodoTask::class);
    }

    /**
     * Returns all tasks grouped by status (Admin view — all assignees).
     * Optionally filtered by a specific assignee.
     *
     * @return array{todo: TodoTask[], in_progress: TodoTask[], done: TodoTask[]}
     */
    public function findAllGroupedByStatus(?User $filterUser = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.assignedTo', 'u')
            ->addSelect('u')
            ->leftJoin('t.createdBy', 'cb')
            ->addSelect('cb')
            ->orderBy('t.status', 'ASC')
            ->addOrderBy('t.position', 'ASC');

        if ($filterUser !== null) {
            $qb->andWhere('t.assignedTo = :user')->setParameter('user', $filterUser);
        }

        $tasks = $qb->getQuery()->getResult();

        return $this->groupByStatus($tasks);
    }

    /**
     * Returns tasks assigned to a specific user, grouped by status (Consultant view).
     *
     * @return array{todo: TodoTask[], in_progress: TodoTask[], done: TodoTask[]}
     */
    public function findByAssigneeGroupedByStatus(User $user): array
    {
        $tasks = $this->createQueryBuilder('t')
            ->andWhere('t.assignedTo = :user')
            ->setParameter('user', $user)
            ->orderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->groupByStatus($tasks);
    }

    /**
     * Returns the max position within a column (for appending new tasks).
     */
    public function getMaxPositionForStatus(string $status): int
    {
        $result = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : -1;
    }

    /**
     * @param TodoTask[] $tasks
     * @return array{todo: TodoTask[], in_progress: TodoTask[], done: TodoTask[]}
     */
    private function groupByStatus(array $tasks): array
    {
        $grouped = [
            TodoTask::STATUS_TODO        => [],
            TodoTask::STATUS_IN_PROGRESS => [],
            TodoTask::STATUS_DONE        => [],
        ];

        foreach ($tasks as $task) {
            $grouped[$task->getStatus()][] = $task;
        }

        return $grouped;
    }
}
