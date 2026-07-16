<?php

namespace App\Entity;

use App\Repository\InvoiceLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
class InvoiceLine
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

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private ?string $consumedQuantity = '0';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private ?string $lineTotal = '0';

    #[ORM\ManyToOne(inversedBy: 'invoiceLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne]
    private ?OfferLine $offerLine = null;

    public function getId(): ?int { return $this->id; }
    public function getResourceName(): ?string { return $this->resourceName; }
    public function setResourceName(string $v): static { $this->resourceName = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $v): static { $this->unitPrice = $v; return $this; }
    public function getConsumedQuantity(): ?string { return $this->consumedQuantity; }

    public function setConsumedQuantity(string $v): static
    {
        $this->consumedQuantity = $v;
        $this->calculateTotal();
        return $this;
    }

    public function getLineTotal(): ?string { return $this->lineTotal; }
    public function setLineTotal(string $v): static { $this->lineTotal = $v; return $this; }
    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $v): static { $this->invoice = $v; return $this; }
    public function getOfferLine(): ?OfferLine { return $this->offerLine; }
    public function setOfferLine(?OfferLine $v): static { $this->offerLine = $v; return $this; }

    public function calculateTotal(): void
    {
        $this->lineTotal = bcmul($this->unitPrice ?? '0', $this->consumedQuantity ?? '0', 4);
    }
}
