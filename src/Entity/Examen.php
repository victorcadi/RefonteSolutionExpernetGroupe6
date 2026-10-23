<?php

namespace App\Entity;

use App\Enum\StatutExamen;
use App\Enum\TypeExamen;
use App\Repository\ExamenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Validator\PlanificationExamen;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Session d'examen d'une promotion, avec son jury.
 */
#[ORM\Entity(repositoryClass: ExamenRepository::class)]
#[PlanificationExamen]
class Examen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Indiquez l’intitulé de l’épreuve.')]
    #[Assert\Length(max: 200)]
    private ?string $intitule = null;

    #[ORM\Column(length: 30, enumType: TypeExamen::class)]
    #[Assert\NotNull]
    private ?TypeExamen $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisissez la promotion concernée.')]
    private ?Promotion $promotion = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date et l’heure de l’épreuve.')]
    private ?\DateTimeImmutable $dateExamen = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: 15, max: 600, notInRangeMessage: 'La durée doit être comprise entre {{ min }} et {{ max }} minutes.')]
    private ?int $dureeMinutes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Site $site = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $salle = null;

    /** @var Collection<int, Utilisateur> */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    #[ORM\JoinTable(name: 'examen_jury')]
    #[Assert\Count(min: 2, minMessage: 'Le jury doit compter au moins {{ limit }} membres.')]
    private Collection $jury;

    /** @var Collection<int, EvaluationCompetence> */
    #[ORM\OneToMany(targetEntity: EvaluationCompetence::class, mappedBy: 'examen', orphanRemoval: true)]
    private Collection $evaluations;

    /**
     * Compétences évaluées lors de l'épreuve (lignes de la grille), issues du référentiel de la formation.
     *
     * @var Collection<int, Competence>
     */
    #[ORM\ManyToMany(targetEntity: Competence::class)]
    #[ORM\JoinTable(name: 'examen_competence')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Choisissez au moins une compétence à évaluer.')]
    private Collection $competences;

    /** Clôture par le jury : la grille n'est plus modifiable et les résultats sont visibles des candidats. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $clotureLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $cloturePar = null;

    public function __construct()
    {
        $this->jury = new ArrayCollection();
        $this->evaluations = new ArrayCollection();
        $this->competences = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->intitule;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(?string $intitule): static
    {
        $this->intitule = $intitule;

        return $this;
    }

    public function getType(): ?TypeExamen
    {
        return $this->type;
    }

    public function setType(?TypeExamen $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPromotion(): ?Promotion
    {
        return $this->promotion;
    }

    public function setPromotion(?Promotion $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }

    public function getDateExamen(): ?\DateTimeImmutable
    {
        return $this->dateExamen;
    }

    public function setDateExamen(?\DateTimeImmutable $dateExamen): static
    {
        $this->dateExamen = $dateExamen;

        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(?int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getSalle(): ?string
    {
        return $this->salle;
    }

    public function setSalle(?string $salle): static
    {
        $this->salle = $salle;

        return $this;
    }

    /** @return Collection<int, Utilisateur> */
    public function getJury(): Collection
    {
        return $this->jury;
    }

    public function ajouterMembreJury(Utilisateur $membre): static
    {
        if (!$this->jury->contains($membre)) {
            $this->jury->add($membre);
        }

        return $this;
    }

    public function retirerMembreJury(Utilisateur $membre): static
    {
        $this->jury->removeElement($membre);

        return $this;
    }

    public function estMembreDuJury(Utilisateur $utilisateur): bool
    {
        return $this->jury->contains($utilisateur);
    }

    /** @return Collection<int, EvaluationCompetence> */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    /** Fin de l'épreuve (début + durée). */
    public function getFin(): ?\DateTimeImmutable
    {
        return null === $this->dateExamen || null === $this->dureeMinutes
            ? null
            : $this->dateExamen->modify('+'.$this->dureeMinutes.' minutes');
    }

    /** Le jury peut évaluer dès le début de l'épreuve. */
    public function estCommence(\DateTimeImmutable $maintenant): bool
    {
        return null !== $this->dateExamen && $maintenant >= $this->dateExamen;
    }

    public function getStatut(\DateTimeImmutable $maintenant): StatutExamen
    {
        return match (true) {
            $this->estCloture() => StatutExamen::Cloture,
            $this->estCommence($maintenant) => StatutExamen::EnCorrection,
            default => StatutExamen::AVenir,
        };
    }

    /** Une grille est entamée dès qu'un niveau a été saisi. */
    public function aDesEvaluations(): bool
    {
        return !$this->evaluations->isEmpty();
    }

    /**
     * Identifiants des compétences déjà évaluées pour au moins un candidat.
     *
     * @return list<int>
     */
    public function getCompetencesEvalueesIds(): array
    {
        $ids = [];
        foreach ($this->evaluations as $evaluation) {
            $ids[(int) $evaluation->getCompetence()?->getId()] = true;
        }

        return array_keys($ids);
    }

    public function estCloture(): bool
    {
        return null !== $this->clotureLe;
    }

    public function getClotureLe(): ?\DateTimeImmutable
    {
        return $this->clotureLe;
    }

    public function getCloturePar(): ?Utilisateur
    {
        return $this->cloturePar;
    }

    public function cloturer(Utilisateur $par, \DateTimeImmutable $le): static
    {
        $this->clotureLe = $le;
        $this->cloturePar = $par;

        return $this;
    }

    /** @return Collection<int, Competence> */
    public function getCompetences(): Collection
    {
        return $this->competences;
    }

    public function ajouterCompetence(Competence $competence): static
    {
        if (!$this->competences->contains($competence)) {
            $this->competences->add($competence);
        }

        return $this;
    }

    public function retirerCompetence(Competence $competence): static
    {
        $this->competences->removeElement($competence);

        return $this;
    }

    /**
     * Compétences évaluées regroupées par bloc, dans l'ordre du référentiel.
     *
     * @return array<string, list<Competence>>
     */
    public function getCompetencesParBloc(): array
    {
        $blocs = [];
        foreach ($this->competences as $competence) {
            $blocs[(string) $competence->getBloc()][] = $competence;
        }

        return $blocs;
    }
}
