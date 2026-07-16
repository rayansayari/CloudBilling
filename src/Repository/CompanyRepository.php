<?php
namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Company> */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    public function search(?string $query): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.name', 'ASC');
        if ($query) {
            $qb->andWhere('c.name LIKE :q OR c.email LIKE :q')->setParameter('q', '%'.$query.'%');
        }
        return $qb->getQuery()->getResult();
    }
}
