<?php

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Renseigne le slug des entités qui n'en ont pas encore au moment de leur création.
 * Un slug existant n'est jamais modifié pour ne pas casser les URL publiées.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class SlugListener
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof SluggableInterface || null !== $entity->getSlug()) {
            return;
        }

        $source = (string) $entity->getSlugSource();
        if ('' === trim($source)) {
            return;
        }

        $entity->setSlug($this->slugger->slug($source)->lower()->toString());
    }
}
