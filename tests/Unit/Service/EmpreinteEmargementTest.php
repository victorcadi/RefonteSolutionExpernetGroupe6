<?php

namespace App\Tests\Unit\Service;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Utilisateur;
use App\Service\Emargement\EmpreinteEmargement;
use PHPUnit\Framework\TestCase;

final class EmpreinteEmargementTest extends TestCase
{
    public function testUnEmargementIntactEstReconnu(): void
    {
        $service = new EmpreinteEmargement('secret-de-test');
        $emargement = $this->emargement();
        $emargement->setEmpreinte($service->calculer($emargement));

        self::assertTrue($service->estIntegre($emargement));
    }

    public function testToutChangementDHorodatageOuDeSignatureEstDetecte(): void
    {
        $service = new EmpreinteEmargement('secret-de-test');
        $emargement = $this->emargement();
        $emargement->setEmpreinte($service->calculer($emargement));

        $emargement->setSigneLe($emargement->getSigneLe()->modify('-1 hour'));
        self::assertFalse($service->estIntegre($emargement));

        $autre = $this->emargement();
        $autre->setEmpreinte($service->calculer($autre));
        $autre->setSignature('data:image/png;base64,AAAA');
        self::assertFalse($service->estIntegre($autre));
    }

    public function testLEmpreinteDependDeLaCleSecrete(): void
    {
        $emargement = $this->emargement();

        self::assertNotSame(
            (new EmpreinteEmargement('cle-a'))->calculer($emargement),
            (new EmpreinteEmargement('cle-b'))->calculer($emargement),
        );
    }

    private function emargement(): Emargement
    {
        $creneau = (new Creneau())
            ->setIntitule('API REST')
            ->setDebut(new \DateTimeImmutable('2026-10-22 08:30'))
            ->setFin(new \DateTimeImmutable('2026-10-22 12:00'));

        return (new Emargement())
            ->setCreneau($creneau)
            ->setEtudiant((new Utilisateur())->setEmail('etudiant@demo.expernet.test'))
            ->setSigneLe(new \DateTimeImmutable('2026-10-22 08:41'))
            ->setSignature('data:image/png;base64,iVBORw0KGgo=');
    }
}
