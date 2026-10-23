<?php

namespace App\Security\Voter;

use App\Entity\Creneau;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Accès à la feuille d'émargement d'un créneau : formateur du cours,
 * formateur référent de la promotion ou administrateur.
 *
 * @extends Voter<string, Creneau>
 */
final class FeuilleEmargementVoter extends Voter
{
    public const CONSULTER = 'FEUILLE_EMARGEMENT_CONSULTER';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CONSULTER === $attribute && $subject instanceof Creneau;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }
        if ($this->decisions->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return $subject->getFormateur() === $utilisateur
            || $subject->getPromotion()?->getFormateurReferent() === $utilisateur;
    }
}
