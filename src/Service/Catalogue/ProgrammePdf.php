<?php

namespace App\Service\Catalogue;

use App\Entity\Formation;
use App\Service\Pdf\GenerateurPdf;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fournit le programme d'une formation en PDF.
 *
 * Si un document officiel a été déposé dans public/documents/programmes, il est servi tel quel ;
 * sinon le programme est généré à partir des données de la fiche.
 */
final class ProgrammePdf
{
    public function __construct(
        private readonly GenerateurPdf $pdf,
        #[Autowire('%kernel.project_dir%/public/documents/programmes')]
        private readonly string $dossierProgrammes,
    ) {
    }

    public function cheminDocumentOfficiel(Formation $formation): ?string
    {
        $fichier = $formation->getProgrammePdf();
        if (null === $fichier || !preg_match('/^[a-z0-9-]+\.pdf$/', $fichier)) {
            return null;
        }

        $chemin = $this->dossierProgrammes.'/'.$fichier;

        return is_file($chemin) ? $chemin : null;
    }

    public function generer(Formation $formation): string
    {
        return $this->pdf->depuisGabarit('formation/programme_pdf.html.twig', [
            'formation' => $formation,
            'genere_le' => new \DateTimeImmutable(),
        ]);
    }
}
