<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\TypeFormation;
use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages institutionnelles (mentions légales, confidentialité, accessibilité, plan du site)
 * et fichiers destinés aux moteurs de recherche (sitemap.xml, robots.txt).
 */
final class PageController extends AbstractController
{
    public function __construct(
        private readonly FormationRepository $formations,
        #[Autowire(param: 'expernet.demo')]
        private readonly bool $demo,
    ) {
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'])]
    public function mentionsLegales(): Response
    {
        return $this->render('page/mentions_legales.html.twig');
    }

    #[Route('/confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('page/confidentialite.html.twig');
    }

    #[Route('/accessibilite', name: 'app_accessibilite', methods: ['GET'])]
    public function accessibilite(): Response
    {
        return $this->render('page/accessibilite.html.twig');
    }

    #[Route('/plan-du-site', name: 'app_plan_du_site', methods: ['GET'])]
    public function planDuSite(): Response
    {
        return $this->render('page/plan_du_site.html.twig', [
            'formations_continues' => $this->formations->findParFiliere(TypeFormation::Continue),
            'formations_alternance' => $this->formations->findParFiliere(TypeFormation::Alternance),
        ]);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(): Response
    {
        $reponse = $this->render('page/sitemap.xml.twig', [
            'formations' => $this->formations->requetePubliees()->getQuery()->getResult(),
        ]);
        $reponse->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $reponse->setPublic()->setMaxAge(3600);
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        $reponse = $this->render('page/robots.txt.twig', ['demo' => $this->demo]);
        $reponse->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $reponse->setPublic()->setMaxAge(3600);
    }
}
