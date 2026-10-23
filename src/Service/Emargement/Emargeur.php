<?php

namespace App\Service\Emargement;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Enum\RoleUtilisateur;
use App\Repository\EmargementRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

use function Symfony\Component\String\u;

/**
 * Enregistre la signature de présence d'un étudiant sur un créneau.
 *
 * Règles : l'étudiant appartient à la promotion du créneau, signe pendant le cours,
 * une seule fois, avec une signature recevable. L'horodatage est celui du serveur
 * et l'émargement est scellé (voir EmpreinteEmargement).
 */
final readonly class Emargeur
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmargementRepository $emargements,
        private EmpreinteEmargement $empreinte,
        private SignatureManuscrite $signatureManuscrite,
        private ClockInterface $horloge,
    ) {
    }

    /**
     * @throws EmargementImpossible
     */
    public function signer(Creneau $creneau, Utilisateur $etudiant, ModeSignature $mode, string $signature, ?string $adresseIp = null): Emargement
    {
        $this->verifierPeutSigner($creneau, $etudiant);

        $signature = trim($signature);
        $erreur = match ($mode) {
            ModeSignature::Manuscrite => $this->signatureManuscrite->erreur($signature),
            ModeSignature::Saisie => $this->erreurNomSaisi($signature, $etudiant),
        };
        if (null !== $erreur) {
            throw new EmargementImpossible(EmargementImpossible::SIGNATURE_INVALIDE, $erreur);
        }

        $emargement = (new Emargement())
            ->setCreneau($creneau)
            ->setEtudiant($etudiant)
            ->setSigneLe($this->maintenant())
            ->setMode($mode)
            ->setSignature($signature)
            ->setAdresseIp($adresseIp);
        $this->empreinte->sceller($emargement);

        $this->em->persist($emargement);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés : la contrainte d'unicité en base tranche.
            throw new EmargementImpossible(EmargementImpossible::DEJA_SIGNE, 'Vous avez déjà signé l’émargement de ce cours.');
        }

        return $emargement;
    }

    /**
     * Contrôles préalables, utilisés aussi pour afficher ou non le formulaire.
     *
     * @throws EmargementImpossible
     */
    public function verifierPeutSigner(Creneau $creneau, Utilisateur $etudiant): void
    {
        if (RoleUtilisateur::Etudiant !== $etudiant->getRole()) {
            throw new EmargementImpossible(EmargementImpossible::PAS_ETUDIANT, 'Seuls les étudiants signent l’émargement.');
        }
        if (null === $etudiant->getPromotion() || $etudiant->getPromotion() !== $creneau->getPromotion()) {
            throw new EmargementImpossible(EmargementImpossible::AUTRE_PROMOTION, 'Ce cours ne concerne pas votre promotion.');
        }
        if (null !== $this->emargements->findOneBy(['creneau' => $creneau, 'etudiant' => $etudiant])) {
            throw new EmargementImpossible(EmargementImpossible::DEJA_SIGNE, 'Vous avez déjà signé l’émargement de ce cours.');
        }
        if (!$creneau->estOuvertALaSignature($this->maintenant())) {
            throw new EmargementImpossible(EmargementImpossible::HORS_CRENEAU, 'L’émargement se signe pendant le cours, entre l’heure de début et l’heure de fin.');
        }
    }

    /** Le nom saisi doit correspondre au nom du compte (casse, accents et espaces ignorés). */
    private function erreurNomSaisi(string $nomSaisi, Utilisateur $etudiant): ?string
    {
        if ('' === $nomSaisi) {
            return 'Saisissez votre prénom et votre nom.';
        }
        if (mb_strlen($nomSaisi) > 200) {
            return 'Le nom saisi est trop long.';
        }

        $normaliser = static fn (string $nom): string => u($nom)->ascii()->lower()->collapseWhitespace()->toString();
        $attendus = [
            $normaliser($etudiant->getPrenom().' '.$etudiant->getNom()),
            $normaliser($etudiant->getNom().' '.$etudiant->getPrenom()),
        ];

        return \in_array($normaliser($nomSaisi), $attendus, true)
            ? null
            : 'Le nom saisi ne correspond pas à celui de votre compte ('.$etudiant->getNomComplet().').';
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
