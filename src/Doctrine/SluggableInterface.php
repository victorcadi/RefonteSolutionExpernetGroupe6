<?php

namespace App\Doctrine;

/**
 * Entité dont le slug est généré automatiquement à partir d'un libellé.
 */
interface SluggableInterface
{
    public function getSlug(): ?string;

    public function setSlug(string $slug): static;

    /**
     * Texte à partir duquel le slug est calculé.
     */
    public function getSlugSource(): ?string;
}
