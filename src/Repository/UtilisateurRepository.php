<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Rehash automatique du mot de passe quand l'algorithme évolue.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Nombre de comptes actifs par rôle.
     *
     * @return array<string, int> valeur du rôle => nombre
     */
    public function compterActifsParRole(): array
    {
        $lignes = $this->createQueryBuilder('u')
            ->select('u.role AS role, COUNT(u.id) AS total')
            ->andWhere('u.actif = true')
            ->groupBy('u.role')
            ->getQuery()
            ->getArrayResult();

        $comptes = [];
        foreach ($lignes as $ligne) {
            $role = $ligne['role'] instanceof \BackedEnum ? $ligne['role']->value : (string) $ligne['role'];
            $comptes[$role] = (int) $ligne['total'];
        }

        return $comptes;
    }
}
