<?php

namespace App\Entity;

use App\Repository\CandidatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Personne qui candidate à une formation (alternance ou formation continue).
 *
 * RGPD : la maquette demandait « Êtes-vous porteur de handicap ? ». C'est une donnée
 * de santé (art. 9). On collecte seulement le souhait d'être contacté par la référente
 * handicap, ce qui suffit pour organiser l'accueil.
 */
#[ORM\Entity(repositoryClass: CandidatRepository::class)]
class Candidat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail.')]
    #[Assert\Email(message: 'L’adresse e-mail « {{ value }} » n’est pas valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Indiquez votre numéro de téléphone.')]
    #[Assert\Regex('/^(?:\+|00)?[0-9 .-]{9,18}$/', message: 'Indiquez un numéro de téléphone valide, par exemple 0692 00 00 00.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex('/^\d{5}$/', message: 'Le code postal doit comporter 5 chiffres.')]
    private ?string $codePostal = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $ville = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez votre dernier diplôme, obtenu ou en cours.')]
    #[Assert\Length(max: 255)]
    private ?string $diplome = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $souhaiteContactReferentHandicap = false;

    /** @var Collection<int, Candidature> */
    #[ORM\OneToMany(targetEntity: Candidature::class, mappedBy: 'candidat')]
    private Collection $candidatures;

    public function __construct()
    {
        $this->candidatures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = null === $prenom ? null : trim($prenom);

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = null === $nom ? null : trim($nom);

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = null === $email ? null : mb_strtolower(trim($email));

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = null === $telephone ? null : trim($telephone);

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getDiplome(): ?string
    {
        return $this->diplome;
    }

    public function setDiplome(?string $diplome): static
    {
        $this->diplome = $diplome;

        return $this;
    }

    public function isSouhaiteContactReferentHandicap(): bool
    {
        return $this->souhaiteContactReferentHandicap;
    }

    public function setSouhaiteContactReferentHandicap(bool $souhait): static
    {
        $this->souhaiteContactReferentHandicap = $souhait;

        return $this;
    }

    /** @return Collection<int, Candidature> */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    /**
     * Met à jour les coordonnées à partir d'une nouvelle saisie du même candidat.
     */
    public function mettreAJourDepuis(self $saisie): static
    {
        return $this
            ->setPrenom($saisie->getPrenom())
            ->setNom($saisie->getNom())
            ->setTelephone($saisie->getTelephone())
            ->setAdresse($saisie->getAdresse())
            ->setCodePostal($saisie->getCodePostal())
            ->setVille($saisie->getVille())
            ->setDiplome($saisie->getDiplome())
            ->setSouhaiteContactReferentHandicap($saisie->isSouhaiteContactReferentHandicap());
    }
}
