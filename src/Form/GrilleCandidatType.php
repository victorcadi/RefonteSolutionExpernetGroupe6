<?php

namespace App\Form;

use App\Entity\Competence;
use App\Enum\NiveauAcquisition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Grille d'un candidat : pour chaque compétence, un niveau d'acquisition et un commentaire facultatif.
 * La grille peut être enregistrée en plusieurs fois : une compétence sans niveau reste à évaluer.
 */
final class GrilleCandidatType extends AbstractType
{
    public static function champNiveau(Competence $competence): string
    {
        return 'niveau_'.$competence->getId();
    }

    public static function champCommentaire(Competence $competence): string
    {
        return 'commentaire_'.$competence->getId();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Competence $competence */
        foreach ($options['competences'] as $competence) {
            $builder
                ->add(self::champNiveau($competence), EnumType::class, [
                    'class' => NiveauAcquisition::class,
                    'label' => $competence->getLibelle(),
                    'expanded' => true,
                    'required' => false,
                    'placeholder' => false,
                    'choice_label' => static fn (NiveauAcquisition $niveau): string => $niveau->libelle(),
                    'label_attr' => ['class' => 'fw-bold'],
                ])
                ->add(self::champCommentaire($competence), TextareaType::class, [
                    'label' => 'Commentaire du jury (facultatif)',
                    'required' => false,
                    'attr' => ['rows' => 2, 'maxlength' => 1000],
                    'constraints' => [new Assert\Length(max: 1000)],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('competences');
        $resolver->setAllowedTypes('competences', 'iterable');
        $resolver->setDefaults(['attr' => ['novalidate' => 'novalidate']]);
    }
}
