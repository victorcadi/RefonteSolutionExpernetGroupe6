<?php

namespace App\Form;

use App\Catalogue\CriteresRecherche;
use App\Entity\Filiere;
use App\Enum\Modalite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Filtres à facettes du catalogue (filière, niveau RNCP, CPF, modalité).
 *
 * Formulaire en GET, sans jeton CSRF (lecture seule) : les critères restent dans l'URL.
 * Chaque choix affiche le nombre de formations correspondantes.
 */
final class FiltreCatalogueType extends AbstractType
{
    private const NIVEAUX = [
        5 => 'Niveau 5 (Bac+2)',
        6 => 'Niveau 6 (Bac+3/4)',
        7 => 'Niveau 7 (Bac+5)',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, array<string, int>> $comptes */
        $comptes = $options['comptes'];
        $avecCompte = static fn (string $libelle, string $facette, string $valeur): string => \sprintf('%s (%d)', $libelle, $comptes[$facette][$valeur] ?? 0);

        /** @var list<Filiere> $filieres */
        $filieres = $options['filieres'];
        $choixFilieres = [];
        foreach ($filieres as $filiere) {
            $choixFilieres[$avecCompte((string) $filiere->getNom(), 'filiere', (string) $filiere->getSlug())] = $filiere->getSlug();
        }

        $choixNiveaux = [];
        foreach (self::NIVEAUX as $niveau => $libelle) {
            if (($comptes['niveau'][(string) $niveau] ?? 0) > 0 || $options['afficher_niveaux']) {
                $choixNiveaux[$avecCompte($libelle, 'niveau', (string) $niveau)] = $niveau;
            }
        }

        $builder
            ->add('q', SearchType::class, [
                'label' => 'Rechercher une formation',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : cybersécurité, Excel, développeur…', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('filiere', ChoiceType::class, [
                'label' => 'Filière',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Toutes les filières',
                'choices' => $choixFilieres,
            ])
            ->add('thematique', HiddenType::class, ['required' => false])
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau de certification (RNCP)',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Tous les niveaux',
                'choices' => $choixNiveaux,
            ])
            ->add('cpf', CheckboxType::class, [
                'label' => $avecCompte('Éligible au CPF', 'cpf', '1'),
                'required' => false,
            ])
            ->add('modalite', EnumType::class, [
                'class' => Modalite::class,
                'label' => 'Modalité',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Toutes les modalités',
                'choice_label' => static fn (Modalite $m): string => $avecCompte($m->libelle(), 'modalite', $m->value),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CriteresRecherche::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'comptes' => [],
            'filieres' => [],
            'afficher_niveaux' => true,
        ]);
        $resolver->setAllowedTypes('comptes', 'array');
        $resolver->setAllowedTypes('filieres', 'array');
        $resolver->setAllowedTypes('afficher_niveaux', 'bool');
    }

    /**
     * Pas de préfixe : l'URL reste lisible (?q=excel&cpf=1).
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
