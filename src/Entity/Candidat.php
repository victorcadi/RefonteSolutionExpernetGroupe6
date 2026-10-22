<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Candidat')]
class Candidat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCandidat', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'Nom', type: 'string', nullable: true)]
    private ?string $nom;

    #[ORM\Column(name: 'Prenom', type: 'string', nullable: true)]
    private ?string $prenom;

    #[ORM\Column(name: 'Email', type: 'string', nullable: true)]
    private ?string $email;

    #[ORM\Column(name: 'Telephone', type: 'string', nullable: true)]
    private ?string $telephone;

    #[ORM\Column(name: 'CodePostal', type: 'string', nullable: true)]
    private ?string $codePostal;

    #[ORM\Column(name: 'Ville', type: 'string', nullable: true)]
    private ?string $ville;

    #[ORM\Column(name: 'Handicap', type: 'boolean', nullable: true)]
    private ?bool $handicap;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getHandicap(): ?bool
    {
        return $this->handicap;
    }

    public function setHandicap(?bool $handicap): self
    {
        $this->handicap = $handicap;
        return $this;
    }

}
