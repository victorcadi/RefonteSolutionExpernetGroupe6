<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Emargement')]
class Emargement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idEmargement', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'DateEmargement', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEmargement;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'idUtilisateur', referencedColumnName: 'idUtilisateur', nullable: false)]
    private Utilisateur $utilisateur;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateEmargement(): ?\DateTimeInterface
    {
        return $this->dateEmargement;
    }

    public function setDateEmargement(?\DateTimeInterface $dateEmargement): self
    {
        $this->dateEmargement = $dateEmargement;
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
