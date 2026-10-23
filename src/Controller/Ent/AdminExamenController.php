<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Examen;
use App\Form\ExamenType;
use App\Repository\ExamenRepository;
use App\Security\Voter\ExamenVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Planification des examens par l'administration : épreuve, jury et compétences évaluées.
 */
#[Route('/ent/admin/examens')]
#[IsGranted('ROLE_ADMIN')]
final class AdminExamenController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_ent_admin_examens', methods: ['GET'])]
    public function index(ExamenRepository $examens): Response
    {
        return $this->render('ent/examen/admin/index.html.twig', [
            'examens' => $examens->findTousAvecPromotion(),
            'maintenant' => $this->maintenant(),
        ]);
    }

    #[Route('/nouveau', name: 'app_ent_admin_examen_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        $examen = new Examen();
        $form = $this->createForm(ExamenType::class, $examen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $this->verifierDate($form, null) && $form->isValid()) {
            $this->em->persist($examen);
            $this->em->flush();
            $this->addFlash('success', \sprintf('L’examen « %s » est planifié.', $examen->getIntitule()));

            return $this->redirectToRoute('app_ent_admin_examens', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('ent/examen/admin/formulaire.html.twig', [
            'form' => $form,
            'examen' => null,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifier', name: 'app_ent_admin_examen_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ExamenVoter::MODIFIER, subject: 'examen')]
    public function modifier(Examen $examen, Request $request): Response
    {
        $dateInitiale = $examen->getDateExamen();
        $form = $this->createForm(ExamenType::class, $examen, ['grille_entamee' => $examen->aDesEvaluations()]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $valide = $this->verifierDate($form, $dateInitiale);
            $valide = $this->verifierCompetencesEvaluees($form, $examen) && $valide;
            if ($valide && $form->isValid()) {
                $this->em->flush();
                $this->addFlash('success', \sprintf('L’examen « %s » est modifié.', $examen->getIntitule()));

                return $this->redirectToRoute('app_ent_admin_examens', status: Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('ent/examen/admin/formulaire.html.twig', [
            'form' => $form,
            'examen' => $examen,
            'suppression' => $this->formulaireSuppression($examen),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/supprimer', name: 'app_ent_admin_examen_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExamenVoter::MODIFIER, subject: 'examen')]
    public function supprimer(Examen $examen, Request $request): Response
    {
        $form = $this->formulaireSuppression($examen);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'La demande de suppression n’a pas pu être vérifiée. Réessayez depuis la page de l’examen.');
        } elseif ($examen->aDesEvaluations()) {
            $this->addFlash('danger', 'Cet examen ne peut pas être supprimé : le jury a déjà commencé à remplir les grilles.');
        } else {
            $intitule = $examen->getIntitule();
            $this->em->remove($examen);
            $this->em->flush();
            $this->addFlash('success', \sprintf('L’examen « %s » est supprimé.', $intitule));
        }

        return $this->redirectToRoute('app_ent_admin_examens', status: Response::HTTP_SEE_OTHER);
    }

    private function formulaireSuppression(Examen $examen): FormInterface
    {
        return $this->container->get('form.factory')->createNamedBuilder('suppression_examen')
            ->setAction($this->generateUrl('app_ent_admin_examen_supprimer', ['id' => $examen->getId()]))
            ->getForm();
    }

    /** Un examen ne se planifie pas dans le passé (sauf si sa date n'a pas changé). */
    private function verifierDate(FormInterface $form, ?\DateTimeImmutable $dateInitiale): bool
    {
        $date = $form->get('dateExamen')->getData();
        if (!$date instanceof \DateTimeImmutable || $date == $dateInitiale || $date > $this->maintenant()) {
            return true;
        }
        $form->get('dateExamen')->addError(new FormError('L’examen doit être planifié à une date à venir.'));

        return false;
    }

    /** Une compétence déjà évaluée ne peut plus être retirée de la grille. */
    private function verifierCompetencesEvaluees(FormInterface $form, Examen $examen): bool
    {
        $retenues = array_map(static fn ($c): ?int => $c->getId(), $examen->getCompetences()->toArray());
        $retirees = array_diff($examen->getCompetencesEvalueesIds(), $retenues);
        if ([] === $retirees) {
            return true;
        }
        $form->get('competences')->addError(new FormError('Des compétences déjà évaluées par le jury ne peuvent pas être retirées de la grille.'));

        return false;
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
