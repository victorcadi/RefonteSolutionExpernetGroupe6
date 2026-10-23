<?php

namespace App\Entity;

use App\Enum\TypeElementFiche;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une ligne d'une rubrique de fiche formation (un objectif, un prérequis, un débouché…).
 *
 * Remplace les anciennes tables Prerequis, ObjectifsPedagogiques, MoyensTechHum,
 * ModalitesEvaluation, Debouches, Metiers et Profil qui avaient toutes la même structure.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_element_formation_type', columns: ['formation_id', 'type'])]
class ElementFiche
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\Column(length: 20, enumType: TypeElementFiche::class)]
    #[Assert\NotNull]
    private ?TypeElementFiche $type = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getType(): ?TypeElementFiche
    {
        return $this->type;
    }

    public function setType(TypeElementFiche $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
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
}
