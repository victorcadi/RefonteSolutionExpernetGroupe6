<?php

namespace App\Security\Voter;

use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Repository\PromotionRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Suivi d'une promotion (assiduité des étudiants) : administrateurs,
 * formateur référent et formateurs qui y animent des cours.
 *
 * @extends Voter<string, Promotion>
 */
final class PromotionVoter extends Voter
{
    public const SUIVRE = 'PROMOTION_SUIVRE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly PromotionRepository $promotions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::SUIVRE === $attribute && $subject instanceof Promotion;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        return $this->decisions->decide($token, ['ROLE_ADMIN'])
            || $this->promotions->estSuiviePar($subject, $utilisateur);
    }
}
