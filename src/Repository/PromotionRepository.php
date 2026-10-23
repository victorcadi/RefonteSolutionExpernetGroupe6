<?php

namespace App\Repository;

use App\Entity\Promotion;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    /**
     * Promotions suivies par un formateur : comme référent ou parce qu'il y anime des créneaux.
     *
     * @return list<Promotion>
     */
    public function findSuiviesPar(Utilisateur $formateur): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('App\Entity\Creneau', 'c', 'WITH', 'c.promotion = p')
            ->andWhere('p.formateurReferent = :formateur OR c.formateur = :formateur')
            ->setParameter('formateur', $formateur)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Le formateur est référent de la promotion ou y anime au moins un créneau. */
    public function estSuiviePar(Promotion $promotion, Utilisateur $formateur): bool
    {
        if ($promotion->getFormateurReferent() === $formateur) {
            return true;
        }

        return (bool) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from('App\Entity\Creneau', 'c')
            ->andWhere('c.promotion = :promotion')
            ->andWhere('c.formateur = :formateur')
            ->setParameter('promotion', $promotion)
            ->setParameter('formateur', $formateur)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
