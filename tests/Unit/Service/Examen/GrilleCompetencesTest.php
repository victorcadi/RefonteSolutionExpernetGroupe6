<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Examen;

use App\Entity\Competence;
use App\Entity\EvaluationCompetence;
use App\Entity\Examen;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Enum\NiveauAcquisition;
use App\Enum\StatutExamen;
use App\Service\Examen\GrilleCompetences;
use PHPUnit\Framework\TestCase;

final class GrilleCompetencesTest extends TestCase
{
    private Examen $examen;
    /** @var list<Competence> */
    private array $competences;
    private Utilisateur $lea;
    private Utilisateur $hugo;

    protected function setUp(): void
    {
        $promotion = new Promotion();
        $this->lea = self::avecId(new Utilisateur(), 1)->setPrenom('Léa')->setPromotion($promotion);
        $this->hugo = self::avecId(new Utilisateur(), 2)->setPrenom('Hugo')->setPromotion($promotion);
        $promotion->getEtudiants()->add($this->lea);
        $promotion->getEtudiants()->add($this->hugo);

        $this->examen = (new Examen())->setPromotion($promotion)
            ->setDateExamen(new \DateTimeImmutable('2030-03-11 09:00'))->setDureeMinutes(120);
        $this->competences = [
            self::avecId(new Competence(), 10)->setBloc('Bloc A')->setLibelle('A1'),
            self::avecId(new Competence(), 11)->setBloc('Bloc A')->setLibelle('A2'),
            self::avecId(new Competence(), 20)->setBloc('Bloc B')->setLibelle('B1'),
        ];
        foreach ($this->competences as $competence) {
            $this->examen->ajouterCompetence($competence);
        }
    }

    public function testLaGrilleEstCompleteQuandChaqueCandidatEstEvalueSurChaqueCompetence(): void
    {
        $partielle = $this->grille([
            1 => [10 => NiveauAcquisition::Acquis, 11 => NiveauAcquisition::EnCours, 20 => NiveauAcquisition::Acquis],
            2 => [10 => NiveauAcquisition::Acquis],
        ]);
        self::assertTrue($partielle->estCompletePour($this->lea));
        self::assertSame(1, $partielle->nombreEvaluees($this->hugo));
        self::assertFalse($partielle->estComplete());
        self::assertSame([$this->hugo], $partielle->getCandidatsIncomplets());

        $complete = $this->grille([
            1 => [10 => NiveauAcquisition::Acquis, 11 => NiveauAcquisition::Acquis, 20 => NiveauAcquisition::Acquis],
            2 => [10 => NiveauAcquisition::NonAcquis, 11 => NiveauAcquisition::Acquis, 20 => NiveauAcquisition::EnCours],
        ]);
        self::assertTrue($complete->estComplete());
    }

    public function testLaSyntheseCompteLesCompetencesAcquisesParBloc(): void
    {
        $grille = $this->grille([
            1 => [10 => NiveauAcquisition::Acquis, 11 => NiveauAcquisition::EnCours, 20 => NiveauAcquisition::Acquis],
        ]);

        self::assertSame([
            'Bloc A' => ['acquises' => 1, 'total' => 2],
            'Bloc B' => ['acquises' => 1, 'total' => 1],
        ], $grille->syntheseParBloc($this->lea));
        self::assertSame(['acquises' => 0, 'total' => 2], $grille->syntheseParBloc($this->hugo)['Bloc A']);
    }

    public function testUneGrilleSansCandidatNEstPasComplete(): void
    {
        $examen = (new Examen())->setPromotion(new Promotion());
        $examen->ajouterCompetence($this->competences[0]);

        self::assertFalse((new GrilleCompetences($examen, []))->estComplete());
    }

    public function testLeStatutSuitLaDateEtLaCloture(): void
    {
        self::assertSame(StatutExamen::AVenir, $this->examen->getStatut(new \DateTimeImmutable('2030-03-11 08:59')));
        self::assertSame(StatutExamen::EnCorrection, $this->examen->getStatut(new \DateTimeImmutable('2030-03-11 09:00')));
        self::assertEquals(new \DateTimeImmutable('2030-03-11 11:00'), $this->examen->getFin());

        $this->examen->cloturer($this->lea, new \DateTimeImmutable('2030-03-12 17:00'));
        self::assertSame(StatutExamen::Cloture, $this->examen->getStatut(new \DateTimeImmutable('2030-03-12 18:00')));
    }

    /**
     * @param array<int, array<int, NiveauAcquisition>> $niveaux par étudiant puis par compétence
     */
    private function grille(array $niveaux): GrilleCompetences
    {
        $evaluations = [];
        foreach ($niveaux as $etudiant => $parCompetence) {
            foreach ($parCompetence as $competence => $niveau) {
                $evaluations[$etudiant][$competence] = (new EvaluationCompetence())->setNiveau($niveau);
            }
        }

        return new GrilleCompetences($this->examen, $evaluations);
    }

    /**
     * @template T of object
     *
     * @param T $entite
     *
     * @return T
     */
    private static function avecId(object $entite, int $id): object
    {
        (new \ReflectionProperty($entite, 'id'))->setValue($entite, $id);

        return $entite;
    }
}
