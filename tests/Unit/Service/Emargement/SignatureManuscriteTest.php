<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Emargement;

use App\Service\Emargement\SignatureManuscrite;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle des signatures tracées envoyées par le navigateur.
 */
final class SignatureManuscriteTest extends TestCase
{
    private SignatureManuscrite $controle;

    protected function setUp(): void
    {
        $this->controle = new SignatureManuscrite();
    }

    public function testUnTraceEstAccepte(): void
    {
        self::assertNull($this->controle->erreur(self::png(400, 150, tracer: true)));
    }

    public function testUnCadreVideEstRefuse(): void
    {
        self::assertStringContainsString('vide', (string) $this->controle->erreur(self::png(400, 150, tracer: false)));
        self::assertStringContainsString('vide', (string) $this->controle->erreur(self::png(400, 150, tracer: false, fondBlanc: true)));
    }

    public function testUnSimplePointEstRefuse(): void
    {
        $image = self::image(400, 150);
        imagefilledellipse($image, 200, 75, 4, 4, imagecolorallocate($image, 15, 58, 75));

        self::assertStringContainsString('vide', (string) $this->controle->erreur(self::dataUrl($image)));
    }

    public function testUneSignatureAbsenteEstRefusee(): void
    {
        self::assertSame('Tracez votre signature dans le cadre.', $this->controle->erreur(''));
    }

    public function testSeulLeFormatPngEstAccepte(): void
    {
        $image = self::image(400, 150);
        ob_start();
        imagejpeg($image);
        $jpeg = 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());

        self::assertNotNull($this->controle->erreur($jpeg));
        self::assertNotNull($this->controle->erreur('data:image/png;base64,'.base64_encode('<svg></svg>')), 'Contenu qui n’est pas un PNG.');
        self::assertNotNull($this->controle->erreur('data:image/png;base64,pas du base64 !'));
        self::assertNotNull($this->controle->erreur('javascript:alert(1)'));
    }

    public function testDesDimensionsAberrantesSontRefusees(): void
    {
        self::assertStringContainsString('dimensions', (string) $this->controle->erreur(self::png(60, 30, tracer: true)));
        self::assertStringContainsString('dimensions', (string) $this->controle->erreur(self::png(3000, 150, tracer: true)));
    }

    public function testUneImageTropLourdeEstRefusee(): void
    {
        self::assertStringContainsString('volumineuse', (string) $this->controle->erreur('data:image/png;base64,'.str_repeat('A', 200_001)));
    }

    private static function png(int $largeur, int $hauteur, bool $tracer, bool $fondBlanc = false): string
    {
        $image = self::image($largeur, $hauteur, $fondBlanc);
        if ($tracer) {
            imagesetthickness($image, 3);
            $encre = imagecolorallocate($image, 15, 58, 75);
            for ($x = 10; $x < $largeur - 14; $x += 4) {
                imageline($image, $x, (int) ($hauteur / 2 + 20 * sin($x / 15)), $x + 4, (int) ($hauteur / 2 + 20 * sin(($x + 4) / 15)), $encre);
            }
        }

        return self::dataUrl($image);
    }

    private static function image(int $largeur, int $hauteur, bool $fondBlanc = false): \GdImage
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $fondBlanc ? imagecolorallocate($image, 255, 255, 255) : imagecolorallocatealpha($image, 255, 255, 255, 127));

        return $image;
    }

    private static function dataUrl(\GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
