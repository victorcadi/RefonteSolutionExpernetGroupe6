<?php

namespace App\Repository;

use App\Entity\EvaluationCompetence;
use App\Entity\Examen;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvaluationCompetence>
 */
class EvaluationCompetenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationCompetence::class);
    }

    /**
     * Évaluations d'un examen, indexées par étudiant puis par compétence.
     *
     * @return array<int, array<int, EvaluationCompetence>>
     */
    public function findPourExamenIndexees(Examen $examen): array
    {
        $index = [];
        foreach ($this->findBy(['examen' => $examen]) as $evaluation) {
            $index[(int) $evaluation->getEtudiant()?->getId()][(int) $evaluation->getCompetence()?->getId()] = $evaluation;
        }

        return $index;
    }

    /**
     * Évaluations d'un étudiant pour plusieurs examens, indexées par examen puis par compétence.
     *
     * @param list<Examen> $examens
     *
     * @return array<int, array<int, EvaluationCompetence>>
     */
    public function findPourEtudiantIndexees(Utilisateur $etudiant, array $examens): array
    {
        if ([] === $examens) {
            return [];
        }

        $index = [];
        foreach ($this->findBy(['etudiant' => $etudiant, 'examen' => $examens]) as $evaluation) {
            $index[(int) $evaluation->getExamen()?->getId()][(int) $evaluation->getCompetence()?->getId()] = $evaluation;
        }

        return $index;
    }
}
