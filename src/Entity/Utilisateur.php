<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Utilisateur')]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idUtilisateur', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'Nom', type: 'string', nullable: true)]
    private ?string $nom;

    #[ORM\Column(name: 'Prenom', type: 'string', nullable: true)]
    private ?string $prenom;

    #[ORM\Column(name: 'Email', type: 'string', nullable: true)]
    private ?string $email;

    #[ORM\Column(name: 'Role', type: 'string', nullable: true)]
    private ?string $role;

    #[ORM\Column(name: 'PeutEtreJure', type: 'boolean', nullable: true)]
    private ?bool $peutEtreJure;

    #[ORM\Column(name: 'MotDePasse', type: 'string', nullable: true)]
    private ?string $motDePasse;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'idFormation', referencedColumnName: 'idFormation', nullable: false)]
    private Formation $formation;

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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getPeutEtreJure(): ?bool
    {
        return $this->peutEtreJure;
    }

    public function setPeutEtreJure(?bool $peutEtreJure): self
    {
        $this->peutEtreJure = $peutEtreJure;
        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(?string $motDePasse): self
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    public function getFormation(): Formation
    {
        return $this->formation;
    }

    public function setFormation(Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }

}
