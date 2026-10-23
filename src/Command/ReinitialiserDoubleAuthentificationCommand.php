<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Support : réinitialise la double authentification d'un compte (téléphone perdu…).
 * La personne devra l'activer de nouveau à sa prochaine connexion.
 */
#[AsCommand(name: 'app:double-authentification:reinitialiser', description: 'Réinitialise la double authentification d’un compte')]
final class ReinitialiserDoubleAuthentificationCommand
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Adresse e-mail du compte')] string $email): int
    {
        $utilisateur = $this->utilisateurs->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (null === $utilisateur) {
            $io->error(sprintf('Aucun compte avec l’adresse %s.', $email));

            return Command::FAILURE;
        }

        $utilisateur->setTotpSecret(null)->setTotpActive(false);
        $this->em->flush();
        $io->success(sprintf('Double authentification réinitialisée pour %s. Elle sera à réactiver à la prochaine connexion.', $utilisateur->getNomComplet()));

        return Command::SUCCESS;
    }
}
