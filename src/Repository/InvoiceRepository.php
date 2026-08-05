<?php
namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Invoice> */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findByProjectAndPeriod(int $projectId, int $year, int $month): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :pid')->setParameter('pid', $projectId)
            ->andWhere('i.year = :y')->setParameter('y', $year)
            ->andWhere('i.month = :m')->setParameter('m', $month)
            ->getQuery()->getOneOrNullResult();
    }

    public function findPreviousInvoice(int $projectId, int $year, int $month): ?Invoice
    {
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        return $this->findByProjectAndPeriod($projectId, $prevYear, $prevMonth);
    }

    public function findPendingValidation(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :s')->setParameter('s', Invoice::STATUS_DRAFT)
            ->orderBy('i.year', 'DESC')->addOrderBy('i.month', 'DESC')
            ->getQuery()->getResult();
    }

    public function getTotalBilledByYear(int $year): string
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.totalTTC) as total')
            ->andWhere('i.year = :y')->setParameter('y', $year)
            ->andWhere('i.status = :s')->setParameter('s', Invoice::STATUS_VALIDATED)
            ->getQuery()->getSingleScalarResult();
        return $result ?? '0';
    }

    public function getMonthlyTotals(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.month, SUM(i.totalTTC) as total, COUNT(i.id) as count')
            ->andWhere('i.year = :y')->setParameter('y', $year)
            ->andWhere('i.status = :s')->setParameter('s', Invoice::STATUS_VALIDATED)
            ->groupBy('i.month')->orderBy('i.month', 'ASC')
            ->getQuery()->getResult();
    }

    public function getMonthlyBreakdownHTvsTTC(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.month, SUM(i.totalHT) as totalHT, SUM(i.totalTTC) as totalTTC')
            ->andWhere('i.year = :y')->setParameter('y', $year)
            ->andWhere('i.status = :s')->setParameter('s', Invoice::STATUS_VALIDATED)
            ->groupBy('i.month')->orderBy('i.month', 'ASC')
            ->getQuery()->getResult();
    }

    public function getMonthlyInvoiceCountByStatus(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.month, i.status, COUNT(i.id) as count')
            ->andWhere('i.year = :y')->setParameter('y', $year)
            ->groupBy('i.month', 'i.status')->orderBy('i.month', 'ASC')
            ->getQuery()->getResult();
    }
}

