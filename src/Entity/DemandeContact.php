<?php

namespace App\Entity;

use App\Enum\StatutTraitement;
use App\Enum\TypeDemande;
use App\Repository\DemandeContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Demande de devis, de renseignements ou prise de contact envoyée depuis le site public.
 */
#[ORM\Entity(repositoryClass: DemandeContactRepository::class)]
#[ORM\Index(name: 'idx_demande_statut', columns: ['statut', 'created_at'])]
class DemandeContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: TypeDemande::class)]
    #[Assert\NotNull]
    private TypeDemande $type = TypeDemande::Contact;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail.')]
    #[Assert\Email(message: 'L’adresse e-mail « {{ value }} » n’est pas valide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex('/^(?:\+|00)?[0-9 .-]{9,18}$/', message: 'Indiquez un numéro de téléphone valide, par exemple 0262 00 00 00.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $entreprise = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 10, max: 3000, minMessage: 'Votre message doit contenir au moins {{ limit }} caractères.')]
    private ?string $message = null;

    #[ORM\Column]
    #[Assert\IsTrue(message: 'Vous devez accepter le traitement de vos données pour envoyer votre demande.')]
    private bool $consentement = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, enumType: StatutTraitement::class)]
    private StatutTraitement $statut = StatutTraitement::Nouvelle;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): TypeDemande
    {
        return $this->type;
    }

    public function setType(TypeDemande $type): static
    {
        $this->type = $type;

        return $this;
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
        $this->telephone = null === $telephone || '' === trim($telephone) ? null : trim($telephone);

        return $this;
    }

    public function getEntreprise(): ?string
    {
        return $this->entreprise;
    }

    public function setEntreprise(?string $entreprise): static
    {
        $this->entreprise = null === $entreprise || '' === trim($entreprise) ? null : trim($entreprise);

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = null === $message ? null : trim($message);

        return $this;
    }

    public function isConsentement(): bool
    {
        return $this->consentement;
    }

    public function setConsentement(bool $consentement): static
    {
        $this->consentement = $consentement;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatut(): StatutTraitement
    {
        return $this->statut;
    }

    public function setStatut(StatutTraitement $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
