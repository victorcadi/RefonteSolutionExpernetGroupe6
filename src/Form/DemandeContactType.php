<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DemandeContact;
use App\Entity\Formation;
use App\Enum\TypeDemande;
use App\Form\Type\ChampPiegeType;
use App\Repository\FormationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demande de devis, de renseignements ou prise de contact.
 */
final class DemandeContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => TypeDemande::class,
                'label' => 'Objet de votre demande',
                'expanded' => true,
                'choice_label' => static fn (TypeDemande $type): string => $type->libelle(),
                'label_attr' => ['class' => 'radio-inline'],
            ])
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
                'required' => false,
                'help' => 'Facultatif. Exemple : 0692 12 34 56',
                'attr' => ['autocomplete' => 'tel', 'maxlength' => 20],
            ])
            ->add('entreprise', TextType::class, [
                'label' => 'Entreprise ou organisme',
                'required' => false,
                'help' => 'Facultatif.',
                'attr' => ['autocomplete' => 'organization', 'maxlength' => 150],
            ])
            ->add('formation', EntityType::class, [
                'class' => Formation::class,
                'label' => 'Formation concernée',
                'required' => false,
                'placeholder' => 'Aucune formation en particulier',
                'query_builder' => static fn (FormationRepository $depot) => $depot->requetePubliees(),
                'choice_label' => 'intitule',
                'group_by' => static fn (Formation $formation): string => $formation->getType()->libelle(),
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message',
                'help' => 'Précisez vos besoins : nombre de participants, dates souhaitées, lieu… (10 caractères minimum)',
                'attr' => ['rows' => 6, 'maxlength' => 3000],
            ])
            ->add('consentement', CheckboxType::class, [
                'label' => 'J’accepte qu’Expernet utilise ces informations pour répondre à ma demande.',
            ])
            ->add('site_web', ChampPiegeType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeContact::class,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
