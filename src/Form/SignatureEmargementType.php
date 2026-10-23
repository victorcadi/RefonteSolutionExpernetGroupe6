<?php

namespace App\Form;

use App\Enum\ModeSignature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Signature de l'émargement d'un cours.
 *
 * Sans JavaScript, seul le nom saisi est proposé (mode absent = saisie) ;
 * le script de la page ajoute le tracé au doigt ou à la souris.
 * Le contenu de la signature est contrôlé par le service Emargeur.
 */
final class SignatureEmargementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', EnumType::class, [
                'class' => ModeSignature::class,
                'label' => 'Comment souhaitez-vous signer ?',
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'choice_label' => static fn (ModeSignature $mode): string => match ($mode) {
                    ModeSignature::Manuscrite => 'Tracer ma signature au doigt ou à la souris',
                    ModeSignature::Saisie => 'Saisir mon prénom et mon nom',
                },
                'choice_attr' => static fn (ModeSignature $mode): array => ['data-action' => 'signature#changerMode'],
            ])
            ->add('signature', HiddenType::class, [
                'label' => 'Signature tracée',
                // L'erreur reste sur ce champ pour s'afficher sous le cadre de signature.
                'error_bubbling' => false,
                'attr' => ['data-signature-target' => 'champ'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Votre prénom et votre nom',
                'required' => false,
                'help' => \sprintf('Tel qu’il figure sur votre compte : « %s ».', $options['nom_attendu']),
                'attr' => ['autocomplete' => 'name', 'maxlength' => 200],
            ])
            ->add('certification', CheckboxType::class, [
                'label' => 'Je certifie avoir assisté à ce cours.',
                'constraints' => [new IsTrue(message: 'Cochez la case pour certifier votre présence.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setRequired('nom_attendu');
        $resolver->setAllowedTypes('nom_attendu', 'string');
    }
}
