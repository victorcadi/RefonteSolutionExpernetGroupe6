<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Debouches')]
class Debouches
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idDebouches', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'LibelleDebouches', type: 'string', nullable: true)]
    private ?string $libelleDebouches;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelleDebouches(): ?string
    {
        return $this->libelleDebouches;
    }

    public function setLibelleDebouches(?string $libelleDebouches): self
    {
        $this->libelleDebouches = $libelleDebouches;
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
