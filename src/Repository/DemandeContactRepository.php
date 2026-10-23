<?php

namespace App\Repository;

use App\Entity\DemandeContact;
use App\Enum\StatutTraitement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeContact>
 */
class DemandeContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeContact::class);
    }

    /**
     * Demandes non encore traitées, les plus récentes d'abord.
     *
     * @return list<DemandeContact>
     */
    public function findNouvelles(int $limite = 5): array
    {
        return $this->findBy(['statut' => StatutTraitement::Nouvelle], ['createdAt' => 'DESC'], $limite);
    }

    public function compterNouvelles(): int
    {
        return $this->count(['statut' => StatutTraitement::Nouvelle]);
    }
}
