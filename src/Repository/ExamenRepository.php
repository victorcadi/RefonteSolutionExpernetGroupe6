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

    /**
     * Examens dont l'horaire chevauche l'intervalle donné (hors examen exclu), avec leur jury.
     *
     * @return list<Examen>
     */
    public function findChevauchant(\DateTimeImmutable $debut, \DateTimeImmutable $fin, ?int $exclure = null): array
    {
        $requete = $this->createQueryBuilder('x')
            ->addSelect('j')
            ->leftJoin('x.jury', 'j')
            ->andWhere('x.dateExamen < :fin')
            ->andWhere("DATE_ADD(x.dateExamen, x.dureeMinutes, 'minute') > :debut")
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);
        if (null !== $exclure) {
            $requete->andWhere('x.id <> :exclure')->setParameter('exclure', $exclure);
        }

        return $requete->getQuery()->getResult();
    }

    /**
     * Tous les examens, du plus récent au plus ancien, avec leur promotion.
     *
     * @return list<Examen>
     */
    public function findTousAvecPromotion(): array
    {
        return $this->createQueryBuilder('x')
            ->addSelect('p')
            ->join('x.promotion', 'p')
            ->orderBy('x.dateExamen', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Examens dont l'utilisateur est membre du jury, du plus récent au plus ancien.
     *
     * @return list<Examen>
     */
    public function findPourMembreDuJury(Utilisateur $membre): array
    {
        return $this->createQueryBuilder('x')
            ->addSelect('p')
            ->join('x.promotion', 'p')
            ->join('x.jury', 'j')
            ->andWhere('j = :membre')
            ->setParameter('membre', $membre)
            ->orderBy('x.dateExamen', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les examens d'une promotion, dans l'ordre chronologique.
     *
     * @return list<Examen>
     */
    public function findPourPromotion(Promotion $promotion): array
    {
        return $this->createQueryBuilder('x')
            ->andWhere('x.promotion = :promotion')
            ->setParameter('promotion', $promotion)
            ->orderBy('x.dateExamen', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
