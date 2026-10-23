<?php

namespace App\Service\Examen;

use App\Entity\EvaluationCompetence;
use App\Entity\Examen;
use App\Entity\Utilisateur;
use App\Enum\NiveauAcquisition;

/**
 * Grille de compétences d'un examen : une ligne par compétence évaluée, une grille par candidat.
 */
final readonly class GrilleCompetences
{
    /**
     * @param array<int, array<int, EvaluationCompetence>> $evaluations indexées par étudiant puis par compétence
     */
    public function __construct(
        public Examen $examen,
        private array $evaluations,
    ) {
    }

    /** @return list<Utilisateur> candidats : étudiants de la promotion */
    public function getCandidats(): array
    {
        return array_values($this->examen->getPromotion()?->getEtudiants()->toArray() ?? []);
    }

    public function evaluation(Utilisateur $candidat, int $competenceId): ?EvaluationCompetence
    {
        return $this->evaluations[(int) $candidat->getId()][$competenceId] ?? null;
    }

    /** Nombre de compétences déjà évaluées pour un candidat. */
    public function nombreEvaluees(Utilisateur $candidat): int
    {
        $total = 0;
        foreach ($this->examen->getCompetences() as $competence) {
            if (null !== $this->evaluation($candidat, (int) $competence->getId())) {
                ++$total;
            }
        }

        return $total;
    }

    public function nombreCompetences(): int
    {
        return $this->examen->getCompetences()->count();
    }

    public function estCompletePour(Utilisateur $candidat): bool
    {
        return $this->nombreEvaluees($candidat) === $this->nombreCompetences();
    }

    /** La grille est complète quand chaque candidat a un niveau pour chaque compétence. */
    public function estComplete(): bool
    {
        foreach ($this->getCandidats() as $candidat) {
            if (!$this->estCompletePour($candidat)) {
                return false;
            }
        }

        return [] !== $this->getCandidats() && $this->nombreCompetences() > 0;
    }

    /** @return list<Utilisateur> candidats dont la grille n'est pas terminée */
    public function getCandidatsIncomplets(): array
    {
        return array_values(array_filter($this->getCandidats(), fn (Utilisateur $c): bool => !$this->estCompletePour($c)));
    }

    /**
     * Synthèse par bloc : nombre de compétences acquises sur le total du bloc.
     *
     * @return array<string, array{acquises: int, total: int}>
     */
    public function syntheseParBloc(Utilisateur $candidat): array
    {
        $synthese = [];
        foreach ($this->examen->getCompetencesParBloc() as $bloc => $competences) {
            $acquises = 0;
            foreach ($competences as $competence) {
                if (NiveauAcquisition::Acquis === $this->evaluation($candidat, (int) $competence->getId())?->getNiveau()) {
                    ++$acquises;
                }
            }
            $synthese[$bloc] = ['acquises' => $acquises, 'total' => \count($competences)];
        }

        return $synthese;
    }
}
