<?php

namespace App\Entity;

use App\Enum\ModeSignature;
use App\Repository\EmargementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Signature de présence d'un étudiant sur un créneau.
 *
 * Une seule signature par étudiant et par créneau (contrainte d'unicité en base).
 * L’empreinte (HMAC-SHA256, voir EmpreinteEmargement) scelle l’étudiant, le créneau, l’horodatage et l’image de la
 * signature : toute modification ultérieure de la ligne devient détectable.
 */
#[ORM\Entity(repositoryClass: EmargementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_emargement_creneau_etudiant', columns: ['creneau_id', 'etudiant_id'])]
class Emargement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'emargements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $etudiant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $signeLe = null;

    #[ORM\Column(length: 20, enumType: ModeSignature::class, options: ['default' => 'manuscrite'])]
    private ModeSignature $mode = ModeSignature::Manuscrite;

    /** Signature tracée (image PNG en data URL) ou nom saisi, selon le mode. */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $signature = null;

    #[ORM\Column(length: 64)]
    private ?string $empreinte = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $adresseIp = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(Creneau $creneau): static
    {
        $this->creneau = $creneau;

        return $this;
    }

    public function getEtudiant(): ?Utilisateur
    {
        return $this->etudiant;
    }

    public function setEtudiant(Utilisateur $etudiant): static
    {
        $this->etudiant = $etudiant;

        return $this;
    }

    public function getSigneLe(): ?\DateTimeImmutable
    {
        return $this->signeLe;
    }

    public function setSigneLe(\DateTimeImmutable $signeLe): static
    {
        $this->signeLe = $signeLe;

        return $this;
    }

    public function getMode(): ModeSignature
    {
        return $this->mode;
    }

    public function setMode(ModeSignature $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): static
    {
        $this->signature = $signature;

        return $this;
    }

    public function getEmpreinte(): ?string
    {
        return $this->empreinte;
    }

    public function setEmpreinte(string $empreinte): static
    {
        $this->empreinte = $empreinte;

        return $this;
    }

    public function getAdresseIp(): ?string
    {
        return $this->adresseIp;
    }

    public function setAdresseIp(?string $adresseIp): static
    {
        $this->adresseIp = $adresseIp;

        return $this;
    }
}
