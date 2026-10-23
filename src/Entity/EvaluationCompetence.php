<?php

namespace App\Entity;

use App\Enum\NiveauAcquisition;
use App\Repository\EvaluationCompetenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Case de la grille de compétences : niveau atteint par un candidat sur une compétence lors d'un examen.
 */
#[ORM\Entity(repositoryClass: EvaluationCompetenceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_evaluation', columns: ['examen_id', 'etudiant_id', 'competence_id'])]
class EvaluationCompetence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Examen $examen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $etudiant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Competence $competence = null;

    #[ORM\Column(length: 20, enumType: NiveauAcquisition::class)]
    #[Assert\NotNull]
    private ?NiveauAcquisition $niveau = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $evaluateur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $saisieLe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExamen(): ?Examen
    {
        return $this->examen;
    }

    public function setExamen(Examen $examen): static
    {
        $this->examen = $examen;

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

    public function getCompetence(): ?Competence
    {
        return $this->competence;
    }

    public function setCompetence(Competence $competence): static
    {
        $this->competence = $competence;

        return $this;
    }

    public function getNiveau(): ?NiveauAcquisition
    {
        return $this->niveau;
    }

    public function setNiveau(?NiveauAcquisition $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getEvaluateur(): ?Utilisateur
    {
        return $this->evaluateur;
    }

    public function setEvaluateur(?Utilisateur $evaluateur): static
    {
        $this->evaluateur = $evaluateur;

        return $this;
    }

    public function getSaisieLe(): ?\DateTimeImmutable
    {
        return $this->saisieLe;
    }

    public function setSaisieLe(\DateTimeImmutable $saisieLe): static
    {
        $this->saisieLe = $saisieLe;

        return $this;
    }
}
