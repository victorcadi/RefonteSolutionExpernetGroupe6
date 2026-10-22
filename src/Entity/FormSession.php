<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'FormSession')]
class FormSession
{
    #[ORM\Column(name: 'Localisation', type: 'string', nullable: true)]
    private ?string $localisation;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'idFormation', referencedColumnName: 'idFormation', nullable: false)]
    private Formation $formation;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(name: 'idSession', referencedColumnName: 'idSession', nullable: false)]
    private Session $session;

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): self
    {
        $this->localisation = $localisation;
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
