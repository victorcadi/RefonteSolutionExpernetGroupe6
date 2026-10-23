<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Ent\Assiduite;
use PHPUnit\Framework\TestCase;

final class AssiduiteTest extends TestCase
{
    public function testSansCreneauTermineLeTauxEstInconnu(): void
    {
        $assiduite = new Assiduite(0, 0);

        self::assertNull($assiduite->getTaux());
        self::assertFalse($assiduite->estEnAlerte());
        self::assertSame(0, $assiduite->getAbsences());
    }

    public function testTauxEtAbsences(): void
    {
        $assiduite = new Assiduite(49, 46);

        self::assertSame(93.9, $assiduite->getTaux());
        self::assertSame(3, $assiduite->getAbsences());
        self::assertFalse($assiduite->estEnAlerte());
    }

    public function testAlerteSousLeSeuil(): void
    {
        self::assertTrue((new Assiduite(10, 7))->estEnAlerte());
        self::assertFalse((new Assiduite(10, 8))->estEnAlerte(), 'Le seuil de 80 % est inclus.');
    }

    public function testLeTauxNeDepassePas100(): void
    {
        self::assertSame(100.0, (new Assiduite(4, 5))->getTaux());
        self::assertSame(0, (new Assiduite(4, 5))->getAbsences());
    }
}
