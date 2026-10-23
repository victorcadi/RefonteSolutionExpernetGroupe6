<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * La double authentification est obligatoire dans l'espace formation :
 * tant qu'elle n'est pas activée, seule la page d'activation est accessible.
 */
final readonly class ExigenceDoubleAuthentificationSubscriber implements EventSubscriberInterface
{
    public const ROUTE_ACTIVATION = 'app_ent_double_authentification';

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8) pour connaître l'utilisateur connecté.
        return [KernelEvents::REQUEST => ['exiger', 4]];
    }

    public function exiger(RequestEvent $event): void
    {
        $requete = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($requete->getPathInfo(), '/ent')) {
            return;
        }

        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur || $utilisateur->isTotpAuthenticationEnabled()) {
            return;
        }

        if (self::ROUTE_ACTIVATION !== $requete->attributes->get('_route')) {
            $event->setResponse(new RedirectResponse($this->urls->generate(self::ROUTE_ACTIVATION)));
        }
    }
}
