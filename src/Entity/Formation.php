<?php

namespace App\Entity;

use App\Doctrine\SluggableInterface;
use App\Enum\Modalite;
use App\Enum\StatutSession;
use App\Enum\TypeElementFiche;
use App\Enum\TypeFormation;
use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formation du catalogue, en formation continue ou en alternance.
 *
 * La filière se déduit de la thématique (pas de stockage redondant, 3NF).
 * Les rubriques listées de la fiche (objectifs, prérequis…) sont des ElementFiche.
 */
#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Index(name: 'idx_formation_catalogue', columns: ['type', 'publiee'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('slug')]
class Formation implements SluggableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $intitule = null;

    #[ORM\Column(length: 200, unique: true)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug ne doit contenir que des minuscules, des chiffres et des tirets.')]
    private ?string $slug = null;

    #[ORM\Column(length: 20, enumType: TypeFormation::class)]
    #[Assert\NotNull]
    private ?TypeFormation $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Thematique $thematique = null;

    /** Accroche affichée sur les cartes et en meta description. */
    #[ORM\Column(length: 300)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private ?string $resume = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $public = null;

    /** Libellé lisible de la durée, ex. « 3 jours (21 heures) ». */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $duree = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $dureeHeures = null;

    #[ORM\Column(length: 20, enumType: Modalite::class)]
    #[Assert\NotNull]
    private Modalite $modalite = Modalite::Presentiel;

    /** Tarif HT en euros ; null = tarif sur devis. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $prix = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $eligibleCpf = false;

    /** Niveau du cadre national des certifications (3 = CAP … 7 = Bac+5). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 3, max: 8)]
    private ?int $niveauRncp = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex('/^RNCP\d{4,6}$/', message: 'Le code doit être au format RNCP12345.')]
    private ?string $codeRncp = null;

    /** Titre ou diplôme préparé (alternance). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certification = null;

    /** Rythme de l'alternance, ex. « 31 semaines en formation et 70 en entreprise ». */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rythme = null;

    /** Processus de sélection, ex. « CV et lettre de motivation, puis entretien ». */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $selection = null;

    /** Nom du fichier PDF du programme dans public/documents/programmes. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex('/^[a-z0-9-]+\.pdf$/')]
    private ?string $programmePdf = null;

    /** Nom du fichier image dans assets/img/formations. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $publiee = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $miseEnAvant = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, ElementFiche> */
    #[ORM\OneToMany(targetEntity: ElementFiche::class, mappedBy: 'formation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $elements;

    /** @var Collection<int, ChapitreProgramme> */
    #[ORM\OneToMany(targetEntity: ChapitreProgramme::class, mappedBy: 'formation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $chapitres;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'formation', cascade: ['persist'])]
    #[ORM\OrderBy(['dateDebut' => 'ASC'])]
    private Collection $sessions;

    /** @var Collection<int, Competence> */
    #[ORM\OneToMany(targetEntity: Competence::class, mappedBy: 'formation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['bloc' => 'ASC', 'id' => 'ASC'])]
    private Collection $competences;

    public function __construct()
    {
        $this->elements = new ArrayCollection();
        $this->chapitres = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->competences = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->intitule;
    }

    #[ORM\PrePersist]
    public function initialiserDates(): void
    {
        $maintenant = new \DateTimeImmutable();
        $this->createdAt ??= $maintenant;
        $this->updatedAt = $maintenant;
    }

    #[ORM\PreUpdate]
    public function majDateModification(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(string $intitule): static
    {
        $this->intitule = $intitule;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugSource(): ?string
    {
        return $this->intitule;
    }

    public function getType(): ?TypeFormation
    {
        return $this->type;
    }

    public function setType(TypeFormation $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isAlternance(): bool
    {
        return TypeFormation::Alternance === $this->type;
    }

    public function getThematique(): ?Thematique
    {
        return $this->thematique;
    }

    public function setThematique(Thematique $thematique): static
    {
        $this->thematique = $thematique;

        return $this;
    }

    public function getFiliere(): ?Filiere
    {
        return $this->thematique?->getFiliere();
    }

    public function getResume(): ?string
    {
        return $this->resume;
    }

    public function setResume(string $resume): static
    {
        $this->resume = $resume;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPublic(): ?string
    {
        return $this->public;
    }

    public function setPublic(string $public): static
    {
        $this->public = $public;

        return $this;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function setDuree(string $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getDureeHeures(): ?int
    {
        return $this->dureeHeures;
    }

    public function setDureeHeures(?int $dureeHeures): static
    {
        $this->dureeHeures = $dureeHeures;

        return $this;
    }

    public function getModalite(): Modalite
    {
        return $this->modalite;
    }

    public function setModalite(Modalite $modalite): static
    {
        $this->modalite = $modalite;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function isEligibleCpf(): bool
    {
        return $this->eligibleCpf;
    }

    public function setEligibleCpf(bool $eligibleCpf): static
    {
        $this->eligibleCpf = $eligibleCpf;

        return $this;
    }

    public function getNiveauRncp(): ?int
    {
        return $this->niveauRncp;
    }

    public function setNiveauRncp(?int $niveauRncp): static
    {
        $this->niveauRncp = $niveauRncp;

        return $this;
    }

    /**
     * Équivalence courante du niveau RNCP (affichage public).
     */
    public function getNiveauLibelle(): ?string
    {
        return match ($this->niveauRncp) {
            3 => 'CAP / BEP',
            4 => 'Bac',
            5 => 'Bac+2',
            6 => 'Bac+3 / Bac+4',
            7 => 'Bac+5',
            8 => 'Bac+8',
            default => null,
        };
    }

    public function getCodeRncp(): ?string
    {
        return $this->codeRncp;
    }

    public function setCodeRncp(?string $codeRncp): static
    {
        $this->codeRncp = $codeRncp;

        return $this;
    }

    public function getCertification(): ?string
    {
        return $this->certification;
    }

    public function setCertification(?string $certification): static
    {
        $this->certification = $certification;

        return $this;
    }

    public function getRythme(): ?string
    {
        return $this->rythme;
    }

    public function setRythme(?string $rythme): static
    {
        $this->rythme = $rythme;

        return $this;
    }

    public function getSelection(): ?string
    {
        return $this->selection;
    }

    public function setSelection(?string $selection): static
    {
        $this->selection = $selection;

        return $this;
    }

    public function getProgrammePdf(): ?string
    {
        return $this->programmePdf;
    }

    public function setProgrammePdf(?string $programmePdf): static
    {
        $this->programmePdf = $programmePdf;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function isPubliee(): bool
    {
        return $this->publiee;
    }

    public function setPubliee(bool $publiee): static
    {
        $this->publiee = $publiee;

        return $this;
    }

    public function isMiseEnAvant(): bool
    {
        return $this->miseEnAvant;
    }

    public function setMiseEnAvant(bool $miseEnAvant): static
    {
        $this->miseEnAvant = $miseEnAvant;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ElementFiche> */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    /**
     * Éléments d'une rubrique de la fiche, dans l'ordre d'affichage.
     *
     * @return list<ElementFiche>
     */
    public function getElementsParType(TypeElementFiche $type): array
    {
        return array_values($this->elements->filter(
            static fn (ElementFiche $element): bool => $element->getType() === $type,
        )->toArray());
    }

    public function ajouterElement(TypeElementFiche $type, string $libelle): static
    {
        $element = (new ElementFiche())
            ->setType($type)
            ->setLibelle($libelle)
            ->setPosition(\count($this->getElementsParType($type)));
        $element->setFormation($this);
        $this->elements->add($element);

        return $this;
    }

    /** @return Collection<int, ChapitreProgramme> */
    public function getChapitres(): Collection
    {
        return $this->chapitres;
    }

    public function ajouterChapitre(ChapitreProgramme $chapitre): static
    {
        if (!$this->chapitres->contains($chapitre)) {
            $chapitre->setFormation($this)->setPosition($this->chapitres->count());
            $this->chapitres->add($chapitre);
        }

        return $this;
    }

    /** @return Collection<int, Session> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function ajouterSession(Session $session): static
    {
        if (!$this->sessions->contains($session)) {
            $session->setFormation($this);
            $this->sessions->add($session);
        }

        return $this;
    }

    /**
     * Sessions ouvertes qui n'ont pas encore commencé, de la plus proche à la plus lointaine.
     *
     * @return list<Session>
     */
    public function getSessionsAVenir(?\DateTimeInterface $aPartirDu = null): array
    {
        $reference = \DateTimeImmutable::createFromInterface($aPartirDu ?? new \DateTimeImmutable('today'));

        $sessions = array_values($this->sessions->filter(
            static fn (Session $s): bool => StatutSession::Annulee !== $s->getStatut()
                && $s->getDateDebut() >= $reference,
        )->toArray());
        usort($sessions, static fn (Session $a, Session $b): int => $a->getDateDebut() <=> $b->getDateDebut());

        return $sessions;
    }

    public function getProchaineSession(): ?Session
    {
        return $this->getSessionsAVenir()[0] ?? null;
    }

    /** @return Collection<int, Competence> */
    public function getCompetences(): Collection
    {
        return $this->competences;
    }

    public function ajouterCompetence(Competence $competence): static
    {
        if (!$this->competences->contains($competence)) {
            $competence->setFormation($this);
            $this->competences->add($competence);
        }

        return $this;
    }
}
