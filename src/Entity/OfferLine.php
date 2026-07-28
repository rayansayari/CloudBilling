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

    /** Nom commercial du service. Ex: "VM Linux Standard" */
    #[ORM\Column(length: 255)]
    private ?string $resourceName = null;

    /** Catégorie cloud : IaaS, PaaS, SaaS, Backup, Storage... */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $serviceType = null;

    /** Identifiant SKU unique. Ex: IAAS-001, BKP-002 */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $skuId = null;

    /** Durée du contrat en mois (1, 12, 24, 36...) */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $term = 1;

    /** Unité de facturation : Heure, Go, VM, Mois... */
    #[ORM\Column(length: 50)]
    private ?string $unit = null;

    /** Nombre d'unités commandées */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $quantity = null;

    /** Prix unitaire HT */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private ?string $unitPrice = null;

    /** Valeur de la remise (% ou montant fixe) */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $discount = null;

    /** Type de remise : 'percent' ou 'fixed' */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $discountType = null;

    /** Description / Détails complémentaires */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'offerLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FinancialOffer $financialOffer = null;

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int { return $this->id; }

    public function getResourceName(): ?string { return $this->resourceName; }
    public function setResourceName(string $v): static { $this->resourceName = $v; return $this; }

    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }

    public function getSkuId(): ?string { return $this->skuId; }
    public function setSkuId(?string $v): static { $this->skuId = $v; return $this; }

    public function getTerm(): int { return $this->term; }
    public function setTerm(int $v): static { $this->term = $v; return $this; }

    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }

    public function getQuantity(): ?string { return $this->quantity; }
    public function setQuantity(?string $v): static { $this->quantity = $v; return $this; }

    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $v): static { $this->unitPrice = $v; return $this; }

    public function getDiscount(): ?string { return $this->discount; }
    public function setDiscount(?string $v): static { $this->discount = $v; return $this; }

    public function getDiscountType(): ?string { return $this->discountType; }
    public function setDiscountType(?string $v): static { $this->discountType = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getFinancialOffer(): ?FinancialOffer { return $this->financialOffer; }
    public function setFinancialOffer(?FinancialOffer $v): static { $this->financialOffer = $v; return $this; }

    // ==================== CALCULATED FIELDS ====================

    /**
     * SousTotal = Quantité × Terme × Prix Unitaire
     */
    public function getSubTotal(): float
    {
        $qty  = (float) ($this->quantity  ?? 1);
        $term = (float) $this->term;
        $pu   = (float) ($this->unitPrice ?? 0);
        return $qty * $term * $pu;
    }

    /**
     * PrixTotal = SousTotal − Remise (selon type)
     */
    public function getTotalPrice(): float
    {
        $subTotal = $this->getSubTotal();
        $discount = (float) ($this->discount ?? 0);

        if ($this->discountType === 'percent') {
            return $subTotal * (1 - $discount / 100);
        } elseif ($this->discountType === 'fixed') {
            return max(0, $subTotal - $discount);
        }

        return $subTotal;
    }

    public function __toString(): string { return $this->resourceName ?? ''; }
}
