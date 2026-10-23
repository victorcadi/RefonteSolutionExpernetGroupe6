<?php

namespace App\Repository;

use App\Entity\Examen;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Examen>
 */
class ExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Examen::class);
    }

    /**
     * Examens à venir d'une promotion.
     *
     * @return list<Examen>
     */
    public function findAVenirPourPromotion(Promotion $promotion, \DateTimeImmutable $maintenant): array
    {
        return $this->createQueryBuilder('x')
            ->andWhere('x.promotion = :promotion')
            ->andWhere('x.dateExamen >= :maintenant')
            ->setParameter('promotion', $promotion)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('x.dateExamen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Examens à venir dont l'utilisateur est membre du jury.
     *
     * @return list<Examen>
     */
    public function findAVenirPourMembreDuJury(Utilisateur $membre, \DateTimeImmutable $maintenant): array
    {
        return $this->createQueryBuilder('x')
            ->addSelect('p')
            ->join('x.promotion', 'p')
            ->join('x.jury', 'j')
            ->andWhere('j = :membre')
            ->andWhere('x.dateExamen >= :maintenant')
            ->setParameter('membre', $membre)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('x.dateExamen', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
