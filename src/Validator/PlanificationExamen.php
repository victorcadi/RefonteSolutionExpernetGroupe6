<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Règles de planification d'un examen : jurés habilités et aucun chevauchement
 * avec une autre épreuve de la même promotion ou d'un même juré.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PlanificationExamen extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
