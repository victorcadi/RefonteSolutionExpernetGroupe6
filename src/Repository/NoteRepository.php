<?php

namespace App\Repository;

use App\Entity\Note;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * Dernières notes d'un étudiant.
     *
     * @return list<Note>
     */
    public function findDernieresPourEtudiant(Utilisateur $etudiant, int $limite = 5): array
    {
        return $this->findBy(['etudiant' => $etudiant], ['date' => 'DESC', 'id' => 'DESC'], $limite);
    }
}
