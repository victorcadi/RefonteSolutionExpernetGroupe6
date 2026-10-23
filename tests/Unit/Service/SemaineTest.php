<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Ent\Semaine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SemaineTest extends TestCase
{
    public function testLaSemaineCommenceLeLundiAMinuit(): void
    {
        $semaine = Semaine::contenant(new \DateTimeImmutable('2026-10-22 15:40'));

        self::assertSame('2026-10-19 00:00', $semaine->lundi->format('Y-m-d H:i'));
        self::assertSame('2026-10-25', $semaine->getDimanche()->format('Y-m-d'));
        self::assertSame('2026-10-26 00:00', $semaine->getFin()->format('Y-m-d H:i'));
        self::assertSame('2026-W43', $semaine->getCode());
        self::assertCount(7, $semaine->getJours());
    }

    public function testUnDimancheAppartientALaSemaineQuiSeTermine(): void
    {
        self::assertSame('2026-10-19', Semaine::contenant(new \DateTimeImmutable('2026-10-25 23:59'))->lundi->format('Y-m-d'));
    }

    public function testNavigationEntreLesAnnees(): void
    {
        $semaine = Semaine::depuisCode('2026-W53', new \DateTimeImmutable('2026-10-22'));

        self::assertSame('2026-12-28', $semaine->lundi->format('Y-m-d'));
        self::assertSame('2027-W01', $semaine->suivante()->getCode());
        self::assertSame('2026-W52', $semaine->precedente()->getCode());
    }

    /** @return iterable<string, array{?string}> */
    public static function codesInvalides(): iterable
    {
        yield 'absent' => [null];
        yield 'format libre' => ['semaine-43'];
        yield 'semaine 0' => ['2026-W00'];
        yield 'semaine 54' => ['2026-W54'];
        yield 'semaine 53 inexistante' => ['2027-W53'];
        yield 'année hors limites' => ['1999-W10'];
    }

    #[DataProvider('codesInvalides')]
    public function testUnCodeInvalideRenvoieLaSemaineEnCours(?string $code): void
    {
        $maintenant = new \DateTimeImmutable('2026-10-22 10:00');

        self::assertSame('2026-W43', Semaine::depuisCode($code, $maintenant)->getCode());
    }

    public function testContient(): void
    {
        $semaine = Semaine::depuisCode('2026-W43', new \DateTimeImmutable());

        self::assertTrue($semaine->contient(new \DateTimeImmutable('2026-10-19 00:00')));
        self::assertTrue($semaine->contient(new \DateTimeImmutable('2026-10-25 23:59')));
        self::assertFalse($semaine->contient(new \DateTimeImmutable('2026-10-26 00:00')));
    }
}
