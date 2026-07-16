<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function log(string $action, string $entityType, ?int $entityId = null, ?string $details = null, ?array $oldData = null, ?array $newData = null): void
    {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDetails($details);
        $log->setOldData($oldData);
        $log->setNewData($newData);
        $log->setUser($this->security->getUser());

        $this->em->persist($log);
        $this->em->flush();
    }
}
