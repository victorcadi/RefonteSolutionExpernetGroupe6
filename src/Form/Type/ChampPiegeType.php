<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Blank;

/**
 * Champ piège anti-robots (« honeypot ») : masqué aux personnes, il doit rester vide.
 * Il est retiré du parcours clavier et des lecteurs d'écran.
 */
final class ChampPiegeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'label' => 'Ne pas remplir ce champ',
            'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            'row_attr' => ['class' => 'champ-piege', 'aria-hidden' => 'true'],
            'constraints' => [new Blank(message: 'Votre envoi n’a pas pu être traité. Réessayez ou contactez-nous par téléphone.')],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
