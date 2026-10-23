<?php

namespace App\Entity;

use App\Repository\PromotionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Groupe d'étudiants qui suit une session de formation.
 */
#[ORM\Entity(repositoryClass: PromotionRepository::class)]
class Promotion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Session $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $formateurReferent = null;

    /** @var Collection<int, Utilisateur> */
    #[ORM\OneToMany(targetEntity: Utilisateur::class, mappedBy: 'promotion')]
    #[ORM\OrderBy(['nom' => 'ASC', 'prenom' => 'ASC'])]
    private Collection $etudiants;

    public function __construct()
    {
        $this->etudiants = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->session?->getFormation();
    }

    public function getFormateurReferent(): ?Utilisateur
    {
        return $this->formateurReferent;
    }

    public function setFormateurReferent(?Utilisateur $formateurReferent): static
    {
        $this->formateurReferent = $formateurReferent;

        return $this;
    }

    /** @return Collection<int, Utilisateur> */
    public function getEtudiants(): Collection
    {
        return $this->etudiants;
    }
}
