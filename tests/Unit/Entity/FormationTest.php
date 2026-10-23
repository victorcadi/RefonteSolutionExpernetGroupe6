<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Filiere;
use App\Entity\Formation;
use App\Entity\Session;
use App\Entity\Thematique;
use App\Enum\StatutSession;
use App\Enum\TypeElementFiche;
use PHPUnit\Framework\TestCase;

final class FormationTest extends TestCase
{
    public function testLaFiliereSeDeduitDeLaThematique(): void
    {
        $filiere = (new Filiere())->setNom('Informatique');
        $formation = (new Formation())->setThematique((new Thematique())->setNom('Réseaux')->setFiliere($filiere));

        self::assertSame($filiere, $formation->getFiliere());
    }

    public function testLesSessionsAVenirExcluentLesSessionsPasseesEtAnnulees(): void
    {
        $formation = new Formation();
        $passee = $this->session('-10 days');
        $annulee = $this->session('+5 days')->setStatut(StatutSession::Annulee);
        $lointaine = $this->session('+60 days');
        $proche = $this->session('+3 days');
        foreach ([$passee, $annulee, $lointaine, $proche] as $session) {
            $formation->ajouterSession($session);
        }

        self::assertSame([$proche, $lointaine], $formation->getSessionsAVenir());
        self::assertSame($proche, $formation->getProchaineSession());
    }

    public function testLesElementsSontRegroupesParRubriqueDansLOrdreDeSaisie(): void
    {
        $formation = (new Formation())
            ->ajouterElement(TypeElementFiche::Objectif, 'Premier objectif')
            ->ajouterElement(TypeElementFiche::Prerequis, 'Un prérequis')
            ->ajouterElement(TypeElementFiche::Objectif, 'Second objectif');

        $objectifs = $formation->getElementsParType(TypeElementFiche::Objectif);

        self::assertCount(2, $objectifs);
        self::assertSame('Premier objectif', $objectifs[0]->getLibelle());
        self::assertSame(1, $objectifs[1]->getPosition());
    }

    public function testLeNiveauRncpEstTraduitEnEquivalenceLisible(): void
    {
        self::assertSame('Bac+3 / Bac+4', (new Formation())->setNiveauRncp(6)->getNiveauLibelle());
        self::assertNull((new Formation())->getNiveauLibelle());
    }

    private function session(string $decalage): Session
    {
        $debut = (new \DateTimeImmutable('today'))->modify($decalage);

        return (new Session())->setDateDebut($debut)->setDateFin($debut->modify('+1 day'));
    }
}
