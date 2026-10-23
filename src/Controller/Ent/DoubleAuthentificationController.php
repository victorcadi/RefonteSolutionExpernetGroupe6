<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Utilisateur;
use App\EventSubscriber\LimiteDoubleAuthentificationSubscriber;
use App\Security\CompteTotpEnAttente;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Activation de la double authentification (application d'authentification TOTP).
 * Obligatoire pour accéder à l'espace formation.
 */
final class DoubleAuthentificationController extends AbstractController
{
    private const CLE_SESSION = 'ent.totp_en_attente';

    #[Route('/ent/securite/double-authentification', name: 'app_ent_double_authentification', methods: ['GET', 'POST'])]
    public function activer(
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        TotpAuthenticatorInterface $totp,
        EntityManagerInterface $em,
        RateLimiterFactoryInterface $doubleAuthentificationLimiter,
    ): Response {
        if ($utilisateur->isTotpAuthenticationEnabled()) {
            return $this->render('ent/securite/double_authentification_active.html.twig');
        }

        // Le secret reste en session tant que la personne n'a pas saisi un premier code valide.
        $session = $request->getSession();
        $secret = $session->get(self::CLE_SESSION);
        if (!\is_string($secret) || '' === $secret) {
            $secret = $totp->generateSecret();
            $session->set(self::CLE_SESSION, $secret);
        }
        $compte = new CompteTotpEnAttente((string) $utilisateur->getEmail(), $secret);

        $form = $this->createFormBuilder(options: ['attr' => ['novalidate' => 'novalidate']])
            ->add('code', TextType::class, [
                'label' => 'Code à 6 chiffres affiché par l’application',
                'help' => 'Le code change toutes les 30 secondes.',
                'attr' => [
                    'inputmode' => 'numeric',
                    'autocomplete' => 'one-time-code',
                    'maxlength' => 6,
                    'class' => 'champ-code',
                ],
                'constraints' => [
                    new NotBlank(message: 'Saisissez le code affiché par l’application.'),
                    new Regex('/^\d{6}$/', message: 'Le code comporte exactement 6 chiffres.'),
                ],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiteur = $doubleAuthentificationLimiter->create(mb_strtolower((string) $utilisateur->getEmail()));
            $code = (string) $form->get('code')->getData();

            if (!LimiteDoubleAuthentificationSubscriber::essaisRestants($limiteur)) {
                $form->get('code')->addError(new FormError(LimiteDoubleAuthentificationSubscriber::MESSAGE));
            } elseif (!$totp->checkCode($compte, $code)) {
                $limiteur->consume();
                $form->get('code')->addError(new FormError('Ce code ne correspond pas. Vérifiez l’heure de votre téléphone et saisissez le code actuellement affiché.'));
            } else {
                $limiteur->reset();
                $utilisateur->setTotpSecret($secret)->setTotpActive(true);
                $em->flush();
                $session->remove(self::CLE_SESSION);
                $this->addFlash('success', 'La double authentification est activée. Le code de l’application vous sera demandé à chaque connexion.');

                return $this->redirectToRoute('app_ent_accueil', status: Response::HTTP_SEE_OTHER);
            }
        }

        $qrContenu = $totp->getQRContent($compte);

        return $this->render('ent/securite/double_authentification.html.twig', [
            'form' => $form,
            'qr_code' => (new SvgWriter())->write(new QrCode(data: $qrContenu, size: 220, margin: 8))->getDataUri(),
            'secret_lisible' => trim(chunk_split($secret, 4, ' ')),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
