<?php

namespace App\Service\Emargement;

/**
 * Contrôle d'une signature tracée envoyée par le navigateur (image PNG en data URL).
 *
 * Le navigateur n'est pas digne de confiance : on vérifie le format, la taille,
 * les dimensions et la présence d'un tracé réel avant d'enregistrer l'image.
 */
final class SignatureManuscrite
{
    private const PREFIXE = 'data:image/png;base64,';
    private const TAILLE_MAX = 200_000;
    private const LARGEUR_MIN = 200;
    private const LARGEUR_MAX = 2400;
    private const HAUTEUR_MIN = 80;
    private const HAUTEUR_MAX = 1200;
    /** Nombre minimal de points tracés (grille de 2 px) pour qu’une signature ne soit pas vide. */
    private const POINTS_TRACES_MIN = 25;

    /**
     * @return string|null message d'erreur à afficher, ou null si la signature est recevable
     */
    public function erreur(string $dataUrl): ?string
    {
        if ('' === $dataUrl) {
            return 'Tracez votre signature dans le cadre.';
        }
        if (!str_starts_with($dataUrl, self::PREFIXE)) {
            return 'La signature doit être une image PNG.';
        }
        if (\strlen($dataUrl) > self::TAILLE_MAX) {
            return 'La signature est trop volumineuse.';
        }

        $binaire = base64_decode(substr($dataUrl, \strlen(self::PREFIXE)), true);
        if (false === $binaire || !str_starts_with($binaire, "\x89PNG\r\n\x1A\n")) {
            return 'La signature doit être une image PNG.';
        }

        $dimensions = @getimagesizefromstring($binaire);
        if (false === $dimensions || \IMAGETYPE_PNG !== $dimensions[2]) {
            return 'La signature doit être une image PNG.';
        }
        [$largeur, $hauteur] = $dimensions;
        if ($largeur < self::LARGEUR_MIN || $largeur > self::LARGEUR_MAX || $hauteur < self::HAUTEUR_MIN || $hauteur > self::HAUTEUR_MAX) {
            return 'Les dimensions de la signature ne sont pas valides.';
        }

        if ($this->compterPointsTraces($binaire, $largeur, $hauteur) < self::POINTS_TRACES_MIN) {
            return 'La signature est vide : tracez votre signature dans le cadre.';
        }

        return null;
    }

    /**
     * Compte les points opaques et foncés sur une grille de 2 px :
     * une zone vide (transparente ou blanche) n'en contient aucun.
     */
    private function compterPointsTraces(string $binaire, int $largeur, int $hauteur): int
    {
        $image = @imagecreatefromstring($binaire);
        if (false === $image) {
            return 0;
        }

        $points = 0;
        for ($y = 0; $y < $hauteur; $y += 2) {
            for ($x = 0; $x < $largeur; $x += 2) {
                $couleur = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $opaque = $couleur['alpha'] < 64;
                $fonce = ($couleur['red'] + $couleur['green'] + $couleur['blue']) < 600;
                // Inutile de parcourir toute l'image une fois le minimum atteint.
                if ($opaque && $fonce && ++$points >= self::POINTS_TRACES_MIN) {
                    return $points;
                }
            }
        }

        return $points;
    }
}
