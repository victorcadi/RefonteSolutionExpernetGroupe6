<?php

namespace App\Entity;

use App\Doctrine\SluggableInterface;
use App\Repository\FiliereRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Grande filière d'Expernet : Informatique, Marketing digital.
 */
#[ORM\Entity(repositoryClass: FiliereRepository::class)]
#[UniqueEntity('slug')]
class Filiere implements SluggableInterface
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

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /** @var Collection<int, Thematique> */
    #[ORM\OneToMany(targetEntity: Thematique::class, mappedBy: 'filiere')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $thematiques;

    public function __construct()
    {
        $this->thematiques = new ArrayCollection();
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /** @return Collection<int, Thematique> */
    public function getThematiques(): Collection
    {
        return $this->thematiques;
    }
}
