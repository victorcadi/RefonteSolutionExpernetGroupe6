<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion d'un compte désactivé, avant même la vérification du mot de passe
 * pour l'accès, et à nouveau après (cas d'un compte désactivé pendant une session).
 */
final class VerificateurCompte implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->verifier($user);
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->verifier($user);
    }

    private function verifier(UserInterface $user): void
    {
        if ($user instanceof Utilisateur && !$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez l’équipe Expernet pour le réactiver.');
        }
    }
}
