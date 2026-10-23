<?php

namespace App\Repository;

use App\Entity\Session;
use App\Enum\StatutSession;
use App\Enum\TypeFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Prochaines sessions ouvertes des formations publiées.
     *
     * @return list<Session>
     */
    public function findProchaines(int $limite = 6, ?TypeFormation $type = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('f', 'si')
            ->join('s.formation', 'f')
            ->leftJoin('s.site', 'si')
            ->andWhere('f.publiee = true')
            ->andWhere('s.statut = :ouverte')
            ->andWhere('s.dateDebut >= :aujourdhui')
            ->setParameter('ouverte', StatutSession::Ouverte)
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->orderBy('s.dateDebut', 'ASC')
            ->setMaxResults($limite);

        if (null !== $type) {
            $qb->andWhere('f.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Prochaine session (non annulée) de chaque formation, en une requête.
     *
     * @param iterable<\App\Entity\Formation> $formations
     *
     * @return array<int, Session> identifiant de formation => session
     */
    public function findProchaineParFormation(iterable $formations): array
    {
        $ids = [];
        foreach ($formations as $formation) {
            $ids[] = $formation->getId();
        }
        if ([] === $ids) {
            return [];
        }

        $sessions = $this->createQueryBuilder('s')
            ->addSelect('si')
            ->leftJoin('s.site', 'si')
            ->andWhere('IDENTITY(s.formation) IN (:ids)')
            ->andWhere('s.statut != :annulee')
            ->andWhere('s.dateDebut >= :aujourdhui')
            ->setParameter('ids', $ids)
            ->setParameter('annulee', StatutSession::Annulee)
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        $prochaines = [];
        foreach ($sessions as $session) {
            $prochaines[$session->getFormation()->getId()] ??= $session;
        }

        return $prochaines;
    }
}
