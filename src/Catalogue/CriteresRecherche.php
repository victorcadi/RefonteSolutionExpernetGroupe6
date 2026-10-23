<?php

namespace App\Catalogue;

use App\Enum\Modalite;
use App\Enum\TypeFormation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Critères de recherche du catalogue, alimentés par la chaîne de requête (GET)
 * pour que chaque recherche ait une URL partageable.
 */
final class CriteresRecherche
{
    public const PAR_PAGE = 9;

    #[Assert\Length(max: 100)]
    public ?string $q = null;

    public ?TypeFormation $type = null;

    /** Slug de la filière. */
    #[Assert\Regex('/^[a-z0-9-]+$/')]
    public ?string $filiere = null;

    /** Slug de la thématique. */
    #[Assert\Regex('/^[a-z0-9-]+$/')]
    public ?string $thematique = null;

    #[Assert\Range(min: 3, max: 8)]
    public ?int $niveau = null;

    public bool $cpf = false;

    public ?Modalite $modalite = null;

    #[Assert\Positive]
    public int $page = 1;

    public function estVide(): bool
    {
        return null === $this->termeRecherche()
            && null === $this->filiere
            && null === $this->thematique
            && null === $this->niveau
            && !$this->cpf
            && null === $this->modalite;
    }

    public function termeRecherche(): ?string
    {
        $terme = trim((string) $this->q);

        return '' === $terme ? null : mb_substr($terme, 0, 100);
    }

    /**
     * Paramètres d'URL non vides (pagination, liens de suppression de filtre).
     *
     * @return array<string, string|int>
     */
    public function versParametres(): array
    {
        return array_filter([
            'q' => $this->termeRecherche(),
            'filiere' => $this->filiere,
            'thematique' => $this->thematique,
            'niveau' => $this->niveau,
            'cpf' => $this->cpf ? 1 : null,
            'modalite' => $this->modalite?->value,
        ], static fn ($valeur): bool => null !== $valeur);
    }
}
