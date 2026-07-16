<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\UniqueConstraint(columns: ['project_id', 'year', 'month'])]
class Invoice
{
    public const STATUS_DRAFT = 'Brouillon';
    public const STATUS_VALIDATED = 'Validée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column]
    private ?int $month = null;

    #[ORM\Column(length: 30)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 5)]
    private ?string $currency = 'TND';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private ?string $totalHT = '0';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $tvaRate = '19.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private ?string $totalTVA = '0';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    private ?string $totalTTC = '0';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne]
    private ?User $validatedBy = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FinancialOffer $financialOffer = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', orphanRemoval: true, cascade: ['persist'])]
    private Collection $invoiceLines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->invoiceLines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): static { $this->reference = $v; return $this; }
    public function getYear(): ?int { return $this->year; }
    public function setYear(int $v): static { $this->year = $v; return $this; }
    public function getMonth(): ?int { return $this->month; }
    public function setMonth(int $v): static { $this->month = $v; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getTotalHT(): ?string { return $this->totalHT; }
    public function setTotalHT(string $v): static { $this->totalHT = $v; return $this; }
    public function getTvaRate(): ?string { return $this->tvaRate; }
    public function setTvaRate(string $v): static { $this->tvaRate = $v; return $this; }
    public function getTotalTVA(): ?string { return $this->totalTVA; }
    public function setTotalTVA(string $v): static { $this->totalTVA = $v; return $this; }
    public function getTotalTTC(): ?string { return $this->totalTTC; }
    public function setTotalTTC(string $v): static { $this->totalTTC = $v; return $this; }
    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeImmutable $v): static { $this->validatedAt = $v; return $this; }
    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $v): static { $this->validatedBy = $v; return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $v): static { $this->project = $v; return $this; }
    public function getFinancialOffer(): ?FinancialOffer { return $this->financialOffer; }
    public function setFinancialOffer(?FinancialOffer $v): static { $this->financialOffer = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, InvoiceLine> */
    public function getInvoiceLines(): Collection { return $this->invoiceLines; }

    public function addInvoiceLine(InvoiceLine $line): static
    {
        if (!$this->invoiceLines->contains($line)) {
            $this->invoiceLines->add($line);
            $line->setInvoice($this);
        }
        return $this;
    }

    public function removeInvoiceLine(InvoiceLine $line): static
    {
        if ($this->invoiceLines->removeElement($line)) {
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }
        return $this;
    }

    public function recalculate(): void
    {
        $totalHT = '0';
        foreach ($this->invoiceLines as $line) {
            $totalHT = bcadd($totalHT, $line->getLineTotal() ?? '0', 4);
        }
        $this->totalHT = $totalHT;
        $this->totalTVA = bcmul($totalHT, bcdiv($this->tvaRate, '100', 6), 4);
        $this->totalTTC = bcadd($this->totalHT, $this->totalTVA, 4);
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function getMonthName(): string
    {
        $months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
            7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
        return $months[$this->month] ?? '';
    }

    public function __toString(): string
    {
        return $this->reference ?? '';
    }
}
