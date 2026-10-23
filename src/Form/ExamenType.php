<?php

namespace App\Form;

use App\Entity\Competence;
use App\Entity\Examen;
use App\Entity\Promotion;
use App\Entity\Site;
use App\Entity\Utilisateur;
use App\Enum\TypeExamen;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Planification d'un examen par l'administration : épreuve, horaire, lieu, jury et grille.
 * Les règles croisées (jurés habilités, chevauchements, référentiel) sont portées par l'entité.
 */
final class ExamenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('intitule', TextType::class, [
                'label' => 'Intitulé de l’épreuve',
                'attr' => ['maxlength' => 200],
            ])
            ->add('type', EnumType::class, [
                'class' => TypeExamen::class,
                'label' => 'Type d’épreuve',
                'placeholder' => 'Choisissez un type',
                'choice_label' => static fn (TypeExamen $type): string => $type->libelle(),
            ])
            ->add('promotion', EntityType::class, [
                'class' => Promotion::class,
                'label' => 'Promotion',
                'placeholder' => 'Choisissez une promotion',
                'choice_label' => 'nom',
                // Une fois les grilles entamées, changer de promotion ou d'horaire fausserait les évaluations.
                'disabled' => $options['grille_entamee'],
                'help' => $options['grille_entamee'] ? 'Des grilles sont déjà remplies : la promotion ne peut plus être modifiée.' : null,
            ])
            ->add('dateExamen', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'disabled' => $options['grille_entamee'],
                'help' => $options['grille_entamee'] ? 'Des grilles sont déjà remplies : l’horaire ne peut plus être modifié.' : null,
            ])
            ->add('dureeMinutes', IntegerType::class, [
                'label' => 'Durée (en minutes)',
                'attr' => ['min' => 15, 'max' => 600, 'step' => 15],
            ])
            ->add('site', EntityType::class, [
                'class' => Site::class,
                'label' => 'Site',
                'required' => false,
                'placeholder' => 'À distance ou non renseigné',
                'choice_label' => 'nom',
            ])
            ->add('salle', TextType::class, [
                'label' => 'Salle',
                'required' => false,
                'attr' => ['maxlength' => 60],
            ])
            ->add('jury', EntityType::class, [
                'class' => Utilisateur::class,
                'label' => 'Membres du jury (au moins deux)',
                'multiple' => true,
                'expanded' => true,
                'choice_label' => 'nomComplet',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('u')
                    ->andWhere('u.peutEtreJure = true')
                    ->andWhere('u.actif = true')
                    ->orderBy('u.nom')
                    ->addOrderBy('u.prenom'),
            ])
            ->add('competences', EntityType::class, [
                'class' => Competence::class,
                'label' => 'Compétences évaluées (lignes de la grille)',
                'help' => 'Uniquement des compétences du référentiel de la formation suivie par la promotion.',
                'multiple' => true,
                'expanded' => true,
                'choice_label' => 'libelle',
                'group_by' => static fn (Competence $c): string => $c->getFormation()?->getIntitule().' – '.$c->getBloc(),
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('c')
                    ->join('c.formation', 'f')
                    ->orderBy('f.intitule')
                    ->addOrderBy('c.id'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Examen::class,
            'grille_entamee' => false,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setAllowedTypes('grille_entamee', 'bool');
    }
}
