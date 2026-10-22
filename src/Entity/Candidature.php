<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Candidature')]
class Candidature
{
    #[ORM\Column(name: 'Message', type: 'string', nullable: true)]
    private ?string $message;

    #[ORM\Column(name: 'Formation', type: 'string', nullable: true)]
    private ?string $formationTexte;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Candidat::class)]
    #[ORM\JoinColumn(name: 'idCandidat', referencedColumnName: 'idCandidat', nullable: false)]
    private Candidat $candidat;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'idFormation', referencedColumnName: 'idFormation', nullable: false)]
    private Formation $formation;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getFormationTexte(): ?string
    {
        return $this->formationTexte;
    }

    public function setFormationTexte(?string $formationTexte): self
    {
        $this->formationTexte = $formationTexte;
        return $this;
    }

    public function getCandidat(): Candidat
    {
        return $this->candidat;
    }

    public function setCandidat(Candidat $candidat): self
    {
        $this->candidat = $candidat;
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
