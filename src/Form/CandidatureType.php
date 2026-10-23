<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Candidature;
use App\Entity\Formation;
use App\Entity\Session;
use App\Form\Type\ChampPiegeType;
use App\Repository\FormationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Candidature (alternance) ou inscription (formation continue).
 *
 * Option « sessions » : sessions à venir de la formation choisie à l'avance ;
 * le choix de la date n'est proposé que dans ce cas.
 */
final class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('formation', EntityType::class, [
                'class' => Formation::class,
                'label' => 'Formation souhaitée',
                'placeholder' => 'Choisissez une formation',
                'query_builder' => static fn (FormationRepository $depot) => $depot->requetePubliees(),
                'choice_label' => 'intitule',
                'group_by' => static fn (Formation $formation): string => $formation->getType()->libelle(),
            ]);

        if ([] !== $options['sessions']) {
            $builder->add('session', EntityType::class, [
                'class' => Session::class,
                'choices' => $options['sessions'],
                'label' => 'Date de démarrage souhaitée',
                'required' => false,
                'placeholder' => 'Pas de préférence',
                'choice_label' => static fn (Session $session): string => sprintf(
                    'Du %s au %s – %s',
                    $session->getDateDebut()->format('d/m/Y'),
                    $session->getDateFin()->format('d/m/Y'),
                    $session->getLieu(),
                ),
            ]);
        }

        $builder
            ->add('candidat', CandidatType::class, ['label' => false])
            ->add('message', TextareaType::class, [
                'label' => 'Votre projet en quelques mots',
                'required' => false,
                'help' => 'Facultatif. Motivations, entreprise d’accueil déjà trouvée, questions…',
                'attr' => ['rows' => 5, 'maxlength' => 3000],
            ])
            ->add('consentement', CheckboxType::class, [
                'label' => 'J’accepte qu’Expernet utilise ces informations pour étudier ma candidature et me recontacter.',
            ])
            ->add('site_web', ChampPiegeType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
            'sessions' => [],
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setAllowedTypes('sessions', Session::class.'[]');
    }
}
