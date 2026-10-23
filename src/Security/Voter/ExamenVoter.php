<?php

namespace App\Security\Voter;

use App\Entity\Examen;
use App\Entity\Utilisateur;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un examen :
 * - CONSULTER : membres du jury et administrateurs ;
 * - EVALUER / CLOTURER : membres du jury, une fois l'épreuve commencée et tant qu'elle n'est pas clôturée ;
 * - MODIFIER : administrateurs, tant que l'examen n'est pas clôturé.
 *
 * @extends Voter<string, Examen>
 */
final class ExamenVoter extends Voter
{
    public const CONSULTER = 'EXAMEN_CONSULTER';
    public const EVALUER = 'EXAMEN_EVALUER';
    public const CLOTURER = 'EXAMEN_CLOTURER';
    public const MODIFIER = 'EXAMEN_MODIFIER';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly ClockInterface $horloge,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::CONSULTER, self::EVALUER, self::CLOTURER, self::MODIFIER], true)
            && $subject instanceof Examen;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        $admin = $this->decisions->decide($token, ['ROLE_ADMIN']);
        $jure = $subject->estMembreDuJury($utilisateur);
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());

        return match ($attribute) {
            self::CONSULTER => $jure || $admin,
            self::EVALUER, self::CLOTURER => $jure && $subject->estCommence($maintenant) && !$subject->estCloture(),
            self::MODIFIER => $admin && !$subject->estCloture(),
            default => false,
        };
    }
}
