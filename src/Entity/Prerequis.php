<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Prerequis')]
class Prerequis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idPrerequis', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'LibellePrerequis', type: 'string', nullable: true)]
    private ?string $libellePrerequis;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibellePrerequis(): ?string
    {
        return $this->libellePrerequis;
    }

    public function setLibellePrerequis(?string $libellePrerequis): self
    {
        $this->libellePrerequis = $libellePrerequis;
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
