<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Emargement;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Service\Emargement\EmpreinteEmargement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sceau des émargements : toute modification d'une donnée signée doit être détectée.
 */
final class EmpreinteEmargementTest extends TestCase
{
    private const SECRET = 'secret-de-test-uniquement';

    public function testUnEmargementScelleEstIntegre(): void
    {
        $empreinte = new EmpreinteEmargement(self::SECRET);
        $emargement = $this->emargement();
        $empreinte->sceller($emargement);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $emargement->getEmpreinte());
        self::assertTrue($empreinte->estIntegre($emargement));
        self::assertSame($emargement->getEmpreinte(), $empreinte->calculer($emargement), 'Le calcul est déterministe.');
    }

    /** @return iterable<string, array{\Closure(Emargement): void}> */
    public static function alterations(): iterable
    {
        yield 'image de la signature' => [static fn (Emargement $e) => $e->setSignature($e->getSignature().'A')];
        yield 'heure de signature' => [static fn (Emargement $e) => $e->setSigneLe($e->getSigneLe()->modify('-1 hour'))];
        yield 'mode de signature' => [static fn (Emargement $e) => $e->setMode(ModeSignature::Saisie)];
        yield 'étudiant (identifiant)' => [static fn (Emargement $e) => self::definirId($e->getEtudiant(), 99)];
        yield 'étudiant (adresse)' => [static fn (Emargement $e) => $e->getEtudiant()->setEmail('autre@demo.expernet.test')];
        yield 'créneau (identifiant)' => [static fn (Emargement $e) => self::definirId($e->getCreneau(), 99)];
        yield 'créneau (début)' => [static fn (Emargement $e) => $e->getCreneau()->setDebut($e->getCreneau()->getDebut()->modify('-1 day'))];
        yield 'créneau (fin)' => [static fn (Emargement $e) => $e->getCreneau()->setFin($e->getCreneau()->getFin()->modify('+1 hour'))];
        yield 'créneau (intitulé)' => [static fn (Emargement $e) => $e->getCreneau()->setIntitule('Autre cours')];
        yield 'empreinte' => [static fn (Emargement $e) => $e->setEmpreinte(str_repeat('0', 64))];
    }

    #[DataProvider('alterations')]
    public function testTouteModificationEstDetectee(\Closure $alterer): void
    {
        $empreinte = new EmpreinteEmargement(self::SECRET);
        $emargement = $this->emargement();
        $empreinte->sceller($emargement);

        $alterer($emargement);

        self::assertFalse($empreinte->estIntegre($emargement));
    }

    public function testLeSceauDependDuSecret(): void
    {
        $emargement = $this->emargement();

        self::assertNotSame(
            (new EmpreinteEmargement(self::SECRET))->calculer($emargement),
            (new EmpreinteEmargement('un-autre-secret'))->calculer($emargement),
        );
    }

    public function testUnSecretVideEstRefuse(): void
    {
        $this->expectException(\LogicException::class);

        new EmpreinteEmargement('');
    }

    private function emargement(): Emargement
    {
        $etudiant = (new Utilisateur())->setNom('Técher')->setPrenom('Lucas')->setEmail('etudiant@demo.expernet.test');
        self::definirId($etudiant, 7);

        $creneau = (new Creneau())
            ->setPromotion(new Promotion())
            ->setIntitule('API REST')
            ->setDebut(new \DateTimeImmutable('2026-10-21 08:30:00'))
            ->setFin(new \DateTimeImmutable('2026-10-21 12:00:00'));
        self::definirId($creneau, 42);

        return (new Emargement())
            ->setCreneau($creneau)
            ->setEtudiant($etudiant)
            ->setSigneLe(new \DateTimeImmutable('2026-10-21 08:41:00'))
            ->setMode(ModeSignature::Manuscrite)
            ->setSignature('data:image/png;base64,iVBORw0KGgo=');
    }

    /** Les identifiants sont attribués par la base : on les simule ici. */
    private static function definirId(object $entite, int $id): void
    {
        (new \ReflectionProperty($entite, 'id'))->setValue($entite, $id);
    }
}
