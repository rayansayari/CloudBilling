<?php
namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findActiveProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Project::STATUS_ACTIVE)
            ->orderBy('p.soNumber', 'ASC')
            ->getQuery()->getResult();
    }

    public function search(?string $query, ?int $companyId = null)
    {
        $qb = $this->createQueryBuilder('p')->join('p.company', 'c')->orderBy('p.soNumber', 'ASC');
        if ($query) {
            $qb->andWhere('p.soNumber LIKE :q OR p.name LIKE :q OR c.name LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }
        if ($companyId) {
            $qb->andWhere('p.company = :cid')->setParameter('cid', $companyId);
        }
        return $qb;
    }
}
