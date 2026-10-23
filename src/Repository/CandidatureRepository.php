<?php

namespace App\Repository;

use App\Entity\Candidature;
use App\Enum\StatutTraitement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidature>
 */
class CandidatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidature::class);
    }

    /**
     * Demandes non encore traitées, les plus récentes d'abord.
     *
     * @return list<Candidature>
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
