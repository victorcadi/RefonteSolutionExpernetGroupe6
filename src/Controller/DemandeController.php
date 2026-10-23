<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\DemandeContact;
use App\Entity\Formation;
use App\Enum\TypeDemande;
use App\Form\CandidatureType;
use App\Form\DemandeContactType;
use App\Repository\CandidatRepository;
use App\Repository\FormationRepository;
use App\Service\Demande\NotificationDemande;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formulaires publics : contact, devis et candidature / inscription.
 */
final class DemandeController extends AbstractController
{
    public function __construct(
        private readonly FormationRepository $formations,
        private readonly EntityManagerInterface $em,
        private readonly NotificationDemande $notification,
        private readonly RateLimiterFactoryInterface $formulairePublicLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        return $this->demande($request, TypeDemande::Contact, 'app_contact');
    }

    #[Route('/devis', name: 'app_devis', methods: ['GET', 'POST'])]
    public function devis(Request $request): Response
    {
        return $this->demande($request, TypeDemande::Devis, 'app_devis');
    }

    #[Route('/candidature', name: 'app_candidature', methods: ['GET', 'POST'])]
    public function candidature(Request $request, CandidatRepository $candidats): Response
    {
        $formation = $this->formationDemandee($request);
        $candidature = (new Candidature())->setFormation($formation);
        $sessions = $formation?->getSessionsAVenir() ?? [];

        $form = $this->createForm(CandidatureType::class, $candidature, ['sessions' => $sessions]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->limiteRespectee($request, $form)) {
            // Un candidat déjà connu (même e-mail) est mis à jour plutôt que dupliqué.
            $saisie = $candidature->getCandidat();
            $existant = $saisie ? $candidats->findOneBy(['email' => $saisie->getEmail()]) : null;
            if (null !== $existant && null !== $saisie) {
                $candidature->setCandidat($existant->mettreAJourDepuis($saisie));
            }
            if ($candidature->getSession()?->getFormation() !== $candidature->getFormation()) {
                $candidature->setSession(null);
            }

            $this->em->persist($candidature);
            $this->em->flush();
            $this->notifier(fn () => $this->notification->candidature($candidature));

            $this->addFlash('success', sprintf(
                'Merci %s, votre %s pour « %s » a bien été envoyée. Un conseiller vous recontactera pour la suite. Un e-mail de confirmation vous a été adressé.',
                $candidature->getCandidat()?->getPrenom(),
                $candidature->getFormation()?->isAlternance() ? 'candidature' : 'demande d’inscription',
                $candidature->getFormation()?->getIntitule(),
            ));

            return $this->redirectToRoute('app_candidature', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('demande/candidature.html.twig', [
            'form' => $form,
            'formation' => $formation,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function demande(Request $request, TypeDemande $type, string $route): Response
    {
        $formation = $this->formationDemandee($request);
        $demande = (new DemandeContact())->setType($type)->setFormation($formation);

        $form = $this->createForm(DemandeContactType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->limiteRespectee($request, $form)) {
            $this->em->persist($demande);
            $this->em->flush();
            $this->notifier(fn () => $this->notification->demandeContact($demande));

            $this->addFlash('success', sprintf(
                'Merci %s, votre demande a bien été envoyée. Notre équipe vous répondra dans les meilleurs délais. Un e-mail de confirmation vous a été adressé.',
                $demande->getPrenom(),
            ));

            return $this->redirectToRoute($route, status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('demande/demande.html.twig', [
            'form' => $form,
            'type' => $type,
            'formation' => $formation,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Formation choisie à l'avance depuis une fiche (?formation=slug).
     */
    private function formationDemandee(Request $request): ?Formation
    {
        $slug = $request->query->getString('formation');

        return '' === $slug ? null : $this->formations->findPublieeParSlug($slug);
    }

    private function limiteRespectee(Request $request, FormInterface $form): bool
    {
        $limite = $this->formulairePublicLimiter->create($request->getClientIp() ?? 'inconnue')->consume();
        if ($limite->isAccepted()) {
            return true;
        }

        $minutes = max(1, (int) ceil(($limite->getRetryAfter()->getTimestamp() - time()) / 60));
        $form->addError(new FormError(sprintf(
            'Vous avez envoyé plusieurs demandes en peu de temps. Réessayez dans %d minute%s ou appelez-nous.',
            $minutes,
            $minutes > 1 ? 's' : '',
        )));

        return false;
    }

    /**
     * La demande est déjà enregistrée : un échec d'envoi d'e-mail ne doit pas la faire échouer.
     */
    private function notifier(callable $envoi): void
    {
        try {
            $envoi();
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Échec de l’envoi des e-mails de notification.', ['exception' => $exception]);
        }
    }
}
