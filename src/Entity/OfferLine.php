<?php

namespace App\Entity;

use App\Repository\OfferLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferLineRepository::class)]
class OfferLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $resourceName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 50)]
    private ?string $unit = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'offerLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FinancialOffer $financialOffer = null;

    public function getId(): ?int { return $this->id; }
    public function getResourceName(): ?string { return $this->resourceName; }
    public function setResourceName(string $v): static { $this->resourceName = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $v): static { $this->unitPrice = $v; return $this; }
    public function getQuantity(): ?string { return $this->quantity; }
    public function setQuantity(?string $v): static { $this->quantity = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getFinancialOffer(): ?FinancialOffer { return $this->financialOffer; }
    public function setFinancialOffer(?FinancialOffer $v): static { $this->financialOffer = $v; return $this; }
    public function __toString(): string { return $this->resourceName ?? ''; }
}
