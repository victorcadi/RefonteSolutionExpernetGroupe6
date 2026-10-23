<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Creneau>
 */
class CreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Creneau::class);
    }

    /**
     * Créneaux d'une promotion entre deux instants, dans l'ordre chronologique.
     *
     * @return list<Creneau>
     */
    public function findPourPromotionEntre(Promotion $promotion, \DateTimeImmutable $debut, \DateTimeImmutable $fin, ?int $limite = null): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('f')
            ->leftJoin('c.formateur', 'f')
            ->andWhere('c.promotion = :promotion')
            ->andWhere('c.fin > :debut')
            ->andWhere('c.debut < :fin')
            ->setParameter('promotion', $promotion)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('c.debut', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prochains créneaux animés par un formateur (y compris celui en cours).
     *
     * @return list<Creneau>
     */
    public function findAVenirPourFormateur(Utilisateur $formateur, \DateTimeImmutable $maintenant, int $limite = 6): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->join('c.promotion', 'p')
            ->andWhere('c.formateur = :formateur')
            ->andWhere('c.fin > :maintenant')
            ->setParameter('formateur', $formateur)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('c.debut', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de créneaux d'une promotion déjà terminés.
     */
    public function compterTerminesPourPromotion(Promotion $promotion, \DateTimeImmutable $maintenant): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.promotion = :promotion')
            ->andWhere('c.fin <= :maintenant')
            ->setParameter('promotion', $promotion)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Créneaux animés par un formateur entre deux instants, avec leur promotion.
     *
     * @return list<Creneau>
     */
    public function findPourFormateurEntre(Utilisateur $formateur, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->join('c.promotion', 'p')
            ->andWhere('c.formateur = :formateur')
            ->andWhere('c.fin > :debut')
            ->andWhere('c.debut < :fin')
            ->setParameter('formateur', $formateur)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('c.debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Premier jour de cours d'une promotion (pour proposer les attestations mensuelles).
     */
    public function findPremierDebutPourPromotion(Promotion $promotion): ?\DateTimeImmutable
    {
        $debut = $this->createQueryBuilder('c')
            ->select('MIN(c.debut)')
            ->andWhere('c.promotion = :promotion')
            ->setParameter('promotion', $promotion)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $debut ? null : new \DateTimeImmutable($debut);
    }
}
