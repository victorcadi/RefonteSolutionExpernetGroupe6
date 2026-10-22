<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Metiers')]
class Metiers
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idMetiers', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'LibelleMetiers', type: 'string', nullable: true)]
    private ?string $libelleMetiers;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelleMetiers(): ?string
    {
        return $this->libelleMetiers;
    }

    public function setLibelleMetiers(?string $libelleMetiers): self
    {
        $this->libelleMetiers = $libelleMetiers;
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
