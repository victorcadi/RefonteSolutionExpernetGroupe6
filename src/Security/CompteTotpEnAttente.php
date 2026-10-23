<?php

declare(strict_types=1);

namespace App\Security;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * Secret TOTP en cours d'activation : il n'est enregistré sur le compte
 * qu'après la saisie d'un premier code valide.
 */
final readonly class CompteTotpEnAttente implements TwoFactorInterface
{
    public function __construct(
        private string $identifiant,
        private string $secret,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->identifiant;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
