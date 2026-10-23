<?php

namespace App\Service\Examen;

use App\Entity\EvaluationCompetence;
use App\Entity\Examen;
use App\Entity\Utilisateur;
use App\Enum\NiveauAcquisition;
use App\Form\GrilleCandidatType;
use App\Repository\EvaluationCompetenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lecture et enregistrement de la grille d'un candidat par un membre du jury.
 */
final readonly class SaisieGrille
{
    public function __construct(
        private EvaluationCompetenceRepository $evaluations,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Données initiales du formulaire de grille.
     *
     * @return array<string, mixed>
     */
    public function donnees(Examen $examen, Utilisateur $candidat): array
    {
        $existantes = $this->indexer($examen, $candidat);
        $donnees = [];
        foreach ($examen->getCompetences() as $competence) {
            $evaluation = $existantes[(int) $competence->getId()] ?? null;
            $donnees[GrilleCandidatType::champNiveau($competence)] = $evaluation?->getNiveau();
            $donnees[GrilleCandidatType::champCommentaire($competence)] = $evaluation?->getCommentaire();
        }

        return $donnees;
    }

    /**
     * Libellés des compétences commentées sans niveau : le commentaire seul n'est pas une évaluation.
     *
     * @param array<string, mixed> $donnees
     *
     * @return list<string> noms des champs en erreur
     */
    public function commentairesSansNiveau(Examen $examen, array $donnees): array
    {
        $champs = [];
        foreach ($examen->getCompetences() as $competence) {
            $commentaire = trim((string) ($donnees[GrilleCandidatType::champCommentaire($competence)] ?? ''));
            if ('' !== $commentaire && null === ($donnees[GrilleCandidatType::champNiveau($competence)] ?? null)) {
                $champs[] = GrilleCandidatType::champNiveau($competence);
            }
        }

        return $champs;
    }

    /**
     * Enregistre la grille : une compétence sans niveau n'est pas (ou plus) évaluée.
     *
     * @param array<string, mixed> $donnees
     *
     * @return int nombre de compétences évaluées pour ce candidat
     */
    public function enregistrer(Examen $examen, Utilisateur $candidat, Utilisateur $evaluateur, array $donnees, \DateTimeImmutable $maintenant): int
    {
        $existantes = $this->indexer($examen, $candidat);
        $evaluees = 0;

        foreach ($examen->getCompetences() as $competence) {
            $evaluation = $existantes[(int) $competence->getId()] ?? null;
            $niveau = $donnees[GrilleCandidatType::champNiveau($competence)] ?? null;
            $commentaire = trim((string) ($donnees[GrilleCandidatType::champCommentaire($competence)] ?? ''));
            $commentaire = '' === $commentaire ? null : $commentaire;

            if (!$niveau instanceof NiveauAcquisition) {
                if (null !== $evaluation) {
                    $this->em->remove($evaluation);
                }
                continue;
            }

            ++$evaluees;
            if (null === $evaluation) {
                $evaluation = (new EvaluationCompetence())
                    ->setExamen($examen)
                    ->setEtudiant($candidat)
                    ->setCompetence($competence);
                $this->em->persist($evaluation);
                $examen->getEvaluations()->add($evaluation);
            } elseif ($evaluation->getNiveau() === $niveau && $evaluation->getCommentaire() === $commentaire) {
                continue; // inchangée : l'auteur et l'heure de saisie sont conservés
            }

            $evaluation
                ->setNiveau($niveau)
                ->setCommentaire($commentaire)
                ->setEvaluateur($evaluateur)
                ->setSaisieLe($maintenant);
        }

        $this->em->flush();

        return $evaluees;
    }

    /** @return array<int, EvaluationCompetence> */
    private function indexer(Examen $examen, Utilisateur $candidat): array
    {
        $index = [];
        foreach ($this->evaluations->findBy(['examen' => $examen, 'etudiant' => $candidat]) as $evaluation) {
            $index[(int) $evaluation->getCompetence()?->getId()] = $evaluation;
        }

        return $index;
    }
}
