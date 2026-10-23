<?php

namespace App\Entity;

use App\Repository\CreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Demi-journée de cours d'une promotion : base du calendrier et de l'émargement.
 */
#[ORM\Entity(repositoryClass: CreneauRepository::class)]
#[ORM\Index(name: 'idx_creneau_debut', columns: ['debut'])]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Promotion $promotion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $formateur = null;

    /** Intitulé du module enseigné. */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $intitule = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $salle = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'debut', message: 'La fin du créneau doit suivre son début.')]
    private ?\DateTimeImmutable $fin = null;

    /** @var Collection<int, Emargement> */
    #[ORM\OneToMany(targetEntity: Emargement::class, mappedBy: 'creneau')]
    private Collection $emargements;

    public function __construct()
    {
        $this->emargements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPromotion(): ?Promotion
    {
        return $this->promotion;
    }

    public function setPromotion(Promotion $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }

    public function getFormateur(): ?Utilisateur
    {
        return $this->formateur;
    }

    public function setFormateur(?Utilisateur $formateur): static
    {
        $this->formateur = $formateur;

        return $this;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(string $intitule): static
    {
        $this->intitule = $intitule;

        return $this;
    }

    public function getSalle(): ?string
    {
        return $this->salle;
    }

    public function setSalle(?string $salle): static
    {
        $this->salle = $salle;

        return $this;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeImmutable $debut): static
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
    }

    public function setFin(\DateTimeImmutable $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getDureeMinutes(): int
    {
        if (null === $this->debut || null === $this->fin) {
            return 0;
        }

        return intdiv($this->fin->getTimestamp() - $this->debut->getTimestamp(), 60);
    }

    /**
     * Un étudiant peut signer du début du créneau jusqu'à sa fin.
     */
    public function estOuvertALaSignature(\DateTimeImmutable $maintenant): bool
    {
        return null !== $this->debut && null !== $this->fin
            && $maintenant >= $this->debut && $maintenant <= $this->fin;
    }

    /** @return Collection<int, Emargement> */
    public function getEmargements(): Collection
    {
        return $this->emargements;
    }
}
