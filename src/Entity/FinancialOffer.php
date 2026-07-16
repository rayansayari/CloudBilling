<?php

namespace App\Entity;

use App\Repository\FinancialOfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialOfferRepository::class)]
class FinancialOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    #[ORM\Column]
    private ?int $version = 1;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $effectiveDate = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(inversedBy: 'financialOffers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, OfferLine> */
    #[ORM\OneToMany(targetEntity: OfferLine::class, mappedBy: 'financialOffer', orphanRemoval: true, cascade: ['persist'])]
    private Collection $offerLines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->offerLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getEffectiveDate(): ?\DateTimeInterface
    {
        return $this->effectiveDate;
    }

    public function setEffectiveDate(\DateTimeInterface $effectiveDate): static
    {
        $this->effectiveDate = $effectiveDate;
        return $this;
    }

    public function isIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
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

    /** @return Collection<int, OfferLine> */
    public function getOfferLines(): Collection
    {
        return $this->offerLines;
    }

    public function addOfferLine(OfferLine $offerLine): static
    {
        if (!$this->offerLines->contains($offerLine)) {
            $this->offerLines->add($offerLine);
            $offerLine->setFinancialOffer($this);
        }
        return $this;
    }

    public function removeOfferLine(OfferLine $offerLine): static
    {
        if ($this->offerLines->removeElement($offerLine)) {
            if ($offerLine->getFinancialOffer() === $this) {
                $offerLine->setFinancialOffer(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Offre v%d - %s', $this->version, $this->fileName);
    }
}
