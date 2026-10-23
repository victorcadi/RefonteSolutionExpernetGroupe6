<?php

namespace App\Twig;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * Fonctions Twig propres au site Expernet.
 */
final class ExpernetExtension
{
    /** @var list<Site>|null */
    private ?array $sites = null;

    public function __construct(private readonly SiteRepository $siteRepository)
    {
    }

    /**
     * Icône SVG (Bootstrap Icons) intégrée au document, décorative par défaut.
     *
     * Si un libellé est fourni, l'icône est annoncée aux technologies d'assistance.
     */
    #[AsTwigFunction('icone', needsEnvironment: true, isSafe: ['html'])]
    public function icone(Environment $twig, string $nom, string $classe = '', ?string $libelle = null): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $nom)) {
            throw new \InvalidArgumentException(\sprintf('Nom d’icône invalide : « %s ».', $nom));
        }

        $svg = $twig->getLoader()->getSourceContext('icons/'.$nom.'.svg')->getCode();
        $accessibilite = null === $libelle
            ? 'aria-hidden="true" focusable="false"'
            : \sprintf('role="img" aria-label="%s"', htmlspecialchars($libelle, \ENT_QUOTES));

        $svg = preg_replace('/\s(width|height|class)="[^"]*"/', '', $svg) ?? $svg;

        return str_replace(
            '<svg ',
            \sprintf('<svg class="icone %s" width="1em" height="1em" %s ', htmlspecialchars($classe, \ENT_QUOTES), $accessibilite),
            trim($svg),
        );
    }

    /**
     * Locaux d'Expernet (pied de page, contact).
     *
     * @return list<Site>
     */
    #[AsTwigFunction('expernet_sites')]
    public function sites(): array
    {
        return $this->sites ??= $this->siteRepository->findBy([], ['departement' => 'ASC', 'id' => 'ASC']);
    }
}
