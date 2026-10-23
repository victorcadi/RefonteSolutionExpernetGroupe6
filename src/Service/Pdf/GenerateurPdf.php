<?php

namespace App\Service\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Transforme un gabarit Twig en document PDF (dompdf).
 *
 * Aucune ressource distante n'est chargée et le PHP intégré est désactivé :
 * seules les images en data URL (signatures) sont affichées.
 */
final readonly class GenerateurPdf
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $contexte
     */
    public function depuisGabarit(string $gabarit, array $contexte = [], string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render($gabarit, $contexte));
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
