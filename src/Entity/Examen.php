<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Examen')]
class Examen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idExamen', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'DateExamen', type: 'datetime', nullable: false)]
    private \DateTimeInterface $dateExamen;

    #[ORM\Column(name: 'Localisation', type: 'string', nullable: false)]
    private string $localisation;

    #[ORM\Column(name: 'Salle', type: 'string', nullable: false)]
    private string $salle;

    #[ORM\Column(name: 'Duree', type: 'string', nullable: false)]
    private string $duree;

    #[ORM\Column(name: 'Nom', type: 'string', nullable: false)]
    private string $nom;

    #[ORM\Column(name: 'Type', type: 'string', nullable: false)]
    private string $type;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'idUtilisateur', referencedColumnName: 'idUtilisateur', nullable: false)]
    private Utilisateur $utilisateur;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateExamen(): \DateTimeInterface
    {
        return $this->dateExamen;
    }

    public function setDateExamen(\DateTimeInterface $dateExamen): self
    {
        $this->dateExamen = $dateExamen;
        return $this;
    }

    public function getLocalisation(): string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): self
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getSalle(): string
    {
        return $this->salle;
    }

    public function setSalle(string $salle): self
    {
        $this->salle = $salle;
        return $this;
    }

    public function getDuree(): string
    {
        return $this->duree;
    }

    public function setDuree(string $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

}
