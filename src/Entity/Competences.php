<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Competences')]
class Competences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCompetences', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'CategorieCompetences', type: 'string', nullable: true)]
    private ?string $categorieCompetences;

    #[ORM\Column(name: 'LibelleCompetences', type: 'string', nullable: true)]
    private ?string $libelleCompetences;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategorieCompetences(): ?string
    {
        return $this->categorieCompetences;
    }

    public function setCategorieCompetences(?string $categorieCompetences): self
    {
        $this->categorieCompetences = $categorieCompetences;
        return $this;
    }

    public function getLibelleCompetences(): ?string
    {
        return $this->libelleCompetences;
    }

    public function setLibelleCompetences(?string $libelleCompetences): self
    {
        $this->libelleCompetences = $libelleCompetences;
        return $this;
    }

    public function getFicheDescriptive(): FicheDescriptive
    {
        return $this->ficheDescriptive;
    }

    public function setFicheDescriptive(FicheDescriptive $ficheDescriptive): self
    {
        $this->ficheDescriptive = $ficheDescriptive;
        return $this;
    }

}
