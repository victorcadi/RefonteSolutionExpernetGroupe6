<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Formation')]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFormation', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'Intitule', type: 'string', nullable: true)]
    private ?string $intitule;

    #[ORM\Column(name: 'CodeRNCP', type: 'string', nullable: true)]
    private ?string $codeRNCP;

    #[ORM\Column(name: 'NiveauRNCP', type: 'integer', nullable: true)]
    private ?int $niveauRNCP;

    #[ORM\Column(name: 'NiveauBAC', type: 'string', nullable: true)]
    private ?string $niveauBAC;

    #[ORM\Column(name: 'Prix', type: 'integer', nullable: true)]
    private ?int $prix;

    #[ORM\Column(name: 'FinancementCPF', type: 'boolean', nullable: true)]
    private ?bool $financementCPF;

    #[ORM\Column(name: 'Departement', type: 'string', nullable: true)]
    private ?string $departement;

    #[ORM\Column(name: 'Categorie', type: 'string', nullable: true)]
    private ?string $categorie;

    #[ORM\Column(name: 'Duree', type: 'string', nullable: true)]
    private ?string $duree;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(name: 'idSession', referencedColumnName: 'idSession', nullable: false)]
    private Session $session;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(?string $intitule): self
    {
        $this->intitule = $intitule;
        return $this;
    }

    public function getCodeRNCP(): ?string
    {
        return $this->codeRNCP;
    }

    public function setCodeRNCP(?string $codeRNCP): self
    {
        $this->codeRNCP = $codeRNCP;
        return $this;
    }

    public function getNiveauRNCP(): ?int
    {
        return $this->niveauRNCP;
    }

    public function setNiveauRNCP(?int $niveauRNCP): self
    {
        $this->niveauRNCP = $niveauRNCP;
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

    public function getPrix(): ?int
    {
        return $this->prix;
    }

    public function setPrix(?int $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getFinancementCPF(): ?bool
    {
        return $this->financementCPF;
    }

    public function setFinancementCPF(?bool $financementCPF): self
    {
        $this->financementCPF = $financementCPF;
        return $this;
    }

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(?string $departement): self
    {
        $this->departement = $departement;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function setDuree(?string $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function setSession(Session $session): self
    {
        $this->session = $session;
        return $this;
    }

}
