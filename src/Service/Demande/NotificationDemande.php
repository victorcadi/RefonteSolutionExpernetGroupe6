<?php

declare(strict_types=1);

namespace App\Service\Demande;

use App\Entity\Candidature;
use App\Entity\DemandeContact;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Envoie, pour chaque demande reçue, un e-mail à l'équipe et un accusé de réception au demandeur.
 */
final readonly class NotificationDemande
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(param: 'expernet.email_equipe')]
        private string $emailEquipe,
        #[Autowire(param: 'expernet.email_expediteur')]
        private string $emailExpediteur,
    ) {
    }

    public function demandeContact(DemandeContact $demande): void
    {
        $demandeur = new Address((string) $demande->getEmail(), $demande->getNomComplet());

        $this->mailer->send($this->email('emails/equipe_demande.html.twig', ['demande' => $demande])
            ->to($this->emailEquipe)
            ->replyTo($demandeur)
            ->subject(sprintf('[Site] %s – %s', $demande->getType()->libelle(), $demande->getNomComplet())));

        $this->mailer->send($this->email('emails/accuse_reception.html.twig', [
            'prenom' => $demande->getPrenom(),
            'objet' => mb_strtolower($demande->getType()->libelle()),
            'formation' => $demande->getFormation(),
        ])
            ->to($demandeur)
            ->subject('Expernet a bien reçu votre demande'));
    }

    public function candidature(Candidature $candidature): void
    {
        $candidat = $candidature->getCandidat();
        $destinataire = new Address((string) $candidat?->getEmail(), (string) $candidat?->getNomComplet());
        $formation = $candidature->getFormation();
        $objet = $formation?->isAlternance() ? 'candidature' : 'demande d’inscription';

        $this->mailer->send($this->email('emails/equipe_candidature.html.twig', ['candidature' => $candidature])
            ->to($this->emailEquipe)
            ->replyTo($destinataire)
            ->subject(sprintf('[Site] %s – %s – %s', ucfirst($objet), $formation?->getIntitule(), $candidat?->getNomComplet())));

        $this->mailer->send($this->email('emails/accuse_reception.html.twig', [
            'prenom' => $candidat?->getPrenom(),
            'objet' => $objet,
            'formation' => $formation,
        ])
            ->to($destinataire)
            ->subject(sprintf('Expernet a bien reçu votre %s', $objet)));
    }

    /** @param array<string, mixed> $contexte */
    private function email(string $gabarit, array $contexte): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->emailExpediteur, 'Expernet'))
            ->htmlTemplate($gabarit)
            ->context($contexte);
    }
}
