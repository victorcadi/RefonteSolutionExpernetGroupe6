<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Candidat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Coordonnées du candidat, intégrées au formulaire de candidature.
 */
final class CandidatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name', 'maxlength' => 100],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name', 'maxlength' => 100],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'help' => 'Format attendu : nom@exemple.fr',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'help' => 'Exemple : 0692 12 34 56',
                'attr' => ['autocomplete' => 'tel', 'maxlength' => 20],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'help' => 'Facultatif.',
                'attr' => ['autocomplete' => 'street-address', 'maxlength' => 255],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'help' => 'Facultatif. 5 chiffres, par exemple 97400',
                'attr' => ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 5],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'help' => 'Facultatif.',
                'attr' => ['autocomplete' => 'address-level2', 'maxlength' => 120],
            ])
            ->add('diplome', TextType::class, [
                'label' => 'Dernier diplôme obtenu ou en cours',
                'help' => 'Exemple : BTS SIO, obtenu en 2025',
                'attr' => ['maxlength' => 255],
            ])
            ->add('souhaiteContactReferentHandicap', CheckboxType::class, [
                'label' => 'Je souhaite être contacté(e) par la référente handicap pour adapter mon accueil ou ma formation.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Candidat::class]);
    }
}
