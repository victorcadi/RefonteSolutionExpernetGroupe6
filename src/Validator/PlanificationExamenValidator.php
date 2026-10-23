<?php

namespace App\Validator;

use App\Entity\Examen;
use App\Enum\RoleUtilisateur;
use App\Repository\ExamenRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PlanificationExamenValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExamenRepository $examens,
    ) {
    }

    public function validate(mixed $examen, Constraint $constraint): void
    {
        if (!$constraint instanceof PlanificationExamen) {
            throw new UnexpectedTypeException($constraint, PlanificationExamen::class);
        }
        if (!$examen instanceof Examen) {
            return;
        }

        foreach ($examen->getJury() as $membre) {
            if (!$membre->isPeutEtreJure() || !$membre->isActif() || RoleUtilisateur::Etudiant === $membre->getRole()) {
                $this->context->buildViolation('{{ nom }} n’est pas habilité(e) à siéger dans un jury.')
                    ->setParameter('{{ nom }}', $membre->getNomComplet())
                    ->atPath('jury')
                    ->addViolation();
            }
        }

        $formation = $examen->getPromotion()?->getFormation();
        foreach ($examen->getCompetences() as $competence) {
            if (null !== $formation && $competence->getFormation() !== $formation) {
                $this->context->buildViolation('La compétence « {{ competence }} » n’appartient pas au référentiel de la formation de cette promotion.')
                    ->setParameter('{{ competence }}', (string) $competence->getLibelle())
                    ->atPath('competences')
                    ->addViolation();
            }
        }

        $debut = $examen->getDateExamen();
        $fin = $examen->getFin();
        if (null === $debut || null === $fin) {
            return;
        }

        foreach ($this->examens->findChevauchant($debut, $fin, $examen->getId()) as $autre) {
            $quand = $autre->getDateExamen()?->format('d/m/Y à H:i');
            if (null !== $examen->getPromotion() && $autre->getPromotion() === $examen->getPromotion()) {
                $this->context->buildViolation('La promotion passe déjà l’épreuve « {{ epreuve }} » le {{ quand }} : les horaires se chevauchent.')
                    ->setParameter('{{ epreuve }}', (string) $autre->getIntitule())
                    ->setParameter('{{ quand }}', (string) $quand)
                    ->atPath('dateExamen')
                    ->addViolation();
            }
            foreach ($examen->getJury() as $membre) {
                if ($autre->estMembreDuJury($membre)) {
                    $this->context->buildViolation('{{ nom }} siège déjà au jury de l’épreuve « {{ epreuve }} » le {{ quand }}.')
                        ->setParameter('{{ nom }}', $membre->getNomComplet())
                        ->setParameter('{{ epreuve }}', (string) $autre->getIntitule())
                        ->setParameter('{{ quand }}', (string) $quand)
                        ->atPath('jury')
                        ->addViolation();
                }
            }
        }
    }
}
