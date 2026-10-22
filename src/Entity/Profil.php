<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Profil')]
class Profil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idProfil', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'NiveauDiplome', type: 'string', nullable: true)]
    private ?string $niveauDiplome;

    #[ORM\Column(name: 'NiveauBAC', type: 'string', nullable: true)]
    private ?string $niveauBAC;

    #[ORM\Column(name: 'Domaine', type: 'string', nullable: true)]
    private ?string $domaine;

    #[ORM\ManyToOne(targetEntity: FicheDescriptive::class)]
    #[ORM\JoinColumn(name: 'idFicheDescriptive', referencedColumnName: 'idFicheDescriptive', nullable: false)]
    private FicheDescriptive $ficheDescriptive;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNiveauDiplome(): ?string
    {
        return $this->niveauDiplome;
    }

    public function setNiveauDiplome(?string $niveauDiplome): self
    {
        $this->niveauDiplome = $niveauDiplome;
        return $this;
    }

    public function getNiveauBAC(): ?string
    {
        return $this->niveauBAC;
    }

    public function setNiveauBAC(?string $niveauBAC): self
    {
        $this->niveauBAC = $niveauBAC;
        return $this;
    }

    public function getDomaine(): ?string
    {
        return $this->domaine;
    }

    public function setDomaine(?string $domaine): self
    {
        $this->domaine = $domaine;
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
