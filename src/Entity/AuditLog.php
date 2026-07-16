<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 100)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $oldData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $newData = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(string $v): static { $this->entityType = $v; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $v): static { $this->entityId = $v; return $this; }
    public function getOldData(): ?array { return $this->oldData; }
    public function setOldData(?array $v): static { $this->oldData = $v; return $this; }
    public function getNewData(): ?array { return $this->newData; }
    public function setNewData(?array $v): static { $this->newData = $v; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $v): static { $this->details = $v; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
