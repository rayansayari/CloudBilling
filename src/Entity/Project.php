<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    public const STATUS_ACTIVE = 'Actif';
    public const STATUS_PAUSED = 'En pause';
    public const STATUS_COMPLETED = 'Terminé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro SO est obligatoire')]
    private ?string $soNumber = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du projet est obligatoire')]
    private ?string $name = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date de début est obligatoire')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(length: 30)]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, FinancialOffer> */
    #[ORM\OneToMany(targetEntity: FinancialOffer::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['version' => 'DESC'])]
    private Collection $financialOffers;

    /** @var Collection<int, Invoice> */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['year' => 'DESC', 'month' => 'DESC'])]
    private Collection $invoices;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->financialOffers = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSoNumber(): ?string
    {
        return $this->soNumber;
    }

    public function setSoNumber(string $soNumber): static
    {
        $this->soNumber = $soNumber;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /** @return Collection<int, FinancialOffer> */
    public function getFinancialOffers(): Collection
    {
        return $this->financialOffers;
    }

    public function getActiveOffer(): ?FinancialOffer
    {
        foreach ($this->financialOffers as $offer) {
            if ($offer->isIsActive()) {
                return $offer;
            }
        }
        return null;
    }

    public function addFinancialOffer(FinancialOffer $financialOffer): static
    {
        if (!$this->financialOffers->contains($financialOffer)) {
            $this->financialOffers->add($financialOffer);
            $financialOffer->setProject($this);
        }
        return $this;
    }

    public function removeFinancialOffer(FinancialOffer $financialOffer): static
    {
        if ($this->financialOffers->removeElement($financialOffer)) {
            if ($financialOffer->getProject() === $this) {
                $financialOffer->setProject(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Invoice> */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setProject($this);
        }
        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            if ($invoice->getProject() === $this) {
                $invoice->setProject(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->soNumber, $this->name);
    }

    public static function getStatusChoices(): array
    {
        return [
            'Actif' => self::STATUS_ACTIVE,
            'En pause' => self::STATUS_PAUSED,
            'Terminé' => self::STATUS_COMPLETED,
        ];
    }
}
