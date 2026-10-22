<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ObjectifsPedagogiques')]
class ObjectifsPedagogiques
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idObjectifsPedagogiques', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'Description', type: 'string', nullable: true)]
    private ?string $description;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
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
