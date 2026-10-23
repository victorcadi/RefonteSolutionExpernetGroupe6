<?php

namespace App\Enum;

/**
 * Univers de formation : formation continue (expernet.re) ou alternance (Expernet Campus).
 */
enum TypeFormation: string
{
    case Continue = 'continue';
    case Alternance = 'alternance';

    public function libelle(): string
    {
        return match ($this) {
            self::Continue => 'Formation continue',
            self::Alternance => 'Formation en alternance',
        };
    }

    public function slugUrl(): string
    {
        return match ($this) {
            self::Continue => 'formation-continue',
            self::Alternance => 'alternance',
        };
    }
}
