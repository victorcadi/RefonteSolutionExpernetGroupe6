<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Limite les codes à 6 chiffres erronés (5 par quart d'heure et par compte)
 * pour empêcher de deviner un code par force brute. Seuls les échecs comptent.
 */
final readonly class LimiteDoubleAuthentificationSubscriber implements EventSubscriberInterface
{
    public const MESSAGE = 'Trop de codes erronés. Patientez quelques minutes avant de réessayer.';

    public function __construct(
        private RateLimiterFactoryInterface $doubleAuthentificationLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::ATTEMPT => 'verifierEssaisRestants',
            TwoFactorAuthenticationEvents::FAILURE => 'compterEchec',
            TwoFactorAuthenticationEvents::SUCCESS => 'reinitialiser',
        ];
    }

    public function verifierEssaisRestants(TwoFactorAuthenticationEvent $event): void
    {
        if (!self::essaisRestants($this->limiteur($event))) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE);
        }
    }

    public function compterEchec(TwoFactorAuthenticationEvent $event): void
    {
        $this->limiteur($event)->consume();
    }

    public function reinitialiser(TwoFactorAuthenticationEvent $event): void
    {
        $this->limiteur($event)->reset();
    }

    /** Vrai s'il reste au moins un essai (consommer 0 jeton ne fait que lire l'état). */
    public static function essaisRestants(LimiterInterface $limiteur): bool
    {
        return $limiteur->consume(0)->getRemainingTokens() > 0;
    }

    private function limiteur(TwoFactorAuthenticationEvent $event): LimiterInterface
    {
        return $this->doubleAuthentificationLimiter->create(mb_strtolower($event->getToken()->getUserIdentifier()));
    }
}
