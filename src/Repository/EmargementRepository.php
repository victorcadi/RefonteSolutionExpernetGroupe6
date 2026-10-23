<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Emargement>
 */
class EmargementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Emargement::class);
    }

    /**
     * Nombre d'émargements d'un étudiant sur des créneaux terminés.
     */
    public function compterSurCreneauxTermines(Utilisateur $etudiant, \DateTimeImmutable $maintenant): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.creneau', 'c')
            ->andWhere('e.etudiant = :etudiant')
            ->andWhere('c.fin <= :maintenant')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Identifiants des créneaux, parmi ceux donnés, déjà signés par l'étudiant.
     *
     * @param iterable<\App\Entity\Creneau> $creneaux
     *
     * @return array<int, true> identifiant du créneau => true
     */
    public function findCreneauxSignes(Utilisateur $etudiant, iterable $creneaux): array
    {
        $ids = [];
        foreach ($creneaux as $creneau) {
            $ids[] = $creneau->getId();
        }
        if ([] === $ids) {
            return [];
        }

        $signes = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.creneau) AS creneau')
            ->andWhere('e.etudiant = :etudiant')
            ->andWhere('e.creneau IN (:ids)')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys(array_map('intval', $signes), true);
    }

    /**
     * Émargements d'un créneau, indexés par identifiant d'étudiant.
     *
     * @return array<int, Emargement>
     */
    public function findPourCreneauParEtudiant(Creneau $creneau): array
    {
        $parEtudiant = [];
        foreach ($this->findBy(['creneau' => $creneau]) as $emargement) {
            $parEtudiant[(int) $emargement->getEtudiant()?->getId()] = $emargement;
        }

        return $parEtudiant;
    }

    /**
     * Émargements d'un étudiant sur les créneaux commençant entre deux instants, indexés par créneau.
     *
     * @return array<int, Emargement>
     */
    public function findPourEtudiantEntre(Utilisateur $etudiant, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $resultats = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->join('e.creneau', 'c')
            ->andWhere('e.etudiant = :etudiant')
            ->andWhere('c.debut >= :debut')
            ->andWhere('c.debut < :fin')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();

        $parCreneau = [];
        foreach ($resultats as $emargement) {
            $parCreneau[(int) $emargement->getCreneau()?->getId()] = $emargement;
        }

        return $parCreneau;
    }
}
