<?php

namespace App\Entity;

use App\Doctrine\SluggableInterface;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Domaine de formation rattaché à une filière (ex. Cybersécurité → Informatique).
 */
#[ORM\Entity(repositoryClass: ThematiqueRepository::class)]
#[UniqueEntity('slug')]
class Thematique implements SluggableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $nom = null;

    #[ORM\Column(length: 140, unique: true)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'thematiques')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Filiere $filiere = null;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugSource(): ?string
    {
        return $this->nom;
    }

    public function getFiliere(): ?Filiere
    {
        return $this->filiere;
    }

    public function setFiliere(Filiere $filiere): static
    {
        $this->filiere = $filiere;

        return $this;
    }
}
