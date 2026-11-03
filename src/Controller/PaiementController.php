<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PaiementController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey
    ) {
    }

    /** Crée la session Stripe et redirige vers la page de paiement. */
    #[Route('/paiement/{id}', name: 'app_paiement', requirements: ['id' => '\d+'])]
    public function payer(
        int $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $reservation = $this->getReservationAutorisee($id, $reservationRepository);

        if ($reservation->isPayee()) {
            $this->addFlash('success', 'Cette réservation est déjà payée.');

            return $this->redirectToRoute('app_profil');
        }

        $nombrePlaces = $reservation->getReservationPlaces()->count();
        $seance = $reservation->getSeance();

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $stripe = new StripeClient($this->stripeSecretKey);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $utilisateur->getEmail(),
            'line_items' => [[
                'quantity' => $nombrePlaces,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => Reservation::PRIX_PLACE * 100, // en centimes
                    'product_data' => [
                        'name' => 'Place - ' . $seance->getFilm()->getNomFilm(),
                    ],
                ],
            ]],
            'success_url' => $this->generateUrl(
                'app_paiement_succes',
                ['id' => $id],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancel_url' => $this->generateUrl(
                'app_paiement_annule',
                ['id' => $id],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        $reservation->setStripeSessionId($session->id);
        $entityManager->flush();

        return new RedirectResponse($session->url);
    }

    /** Retour de Stripe : on vérifie le paiement auprès de Stripe. */
    #[Route('/paiement/{id}/succes', name: 'app_paiement_succes', requirements: ['id' => '\d+'])]
    public function succes(
        int $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $reservation = $this->getReservationAutorisee($id, $reservationRepository);

        if ($reservation->getStripeSessionId() === null) {
            throw $this->createNotFoundException();
        }

        $stripe = new StripeClient($this->stripeSecretKey);
        $session = $stripe->checkout->sessions->retrieve(
            $reservation->getStripeSessionId()
        );

        if ($session->payment_status === 'paid') {
            $reservation->setStatut(Reservation::STATUT_PAYEE);
            $entityManager->flush();

            $this->addFlash('success', 'Paiement accepté ! Votre billet est disponible.');
        } else {
            $this->addFlash('error', 'Le paiement n’a pas été confirmé.');
        }

        return $this->redirectToRoute('app_profil');
    }

    /** Paiement annulé : on libère les places. */
    #[Route('/paiement/{id}/annule', name: 'app_paiement_annule', requirements: ['id' => '\d+'])]
    public function annule(
        int $id,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $reservation = $this->getReservationAutorisee($id, $reservationRepository);
        $seanceId = $reservation->getSeance()->getIdSeance();

        if (!$reservation->isPayee()) {
            foreach ($reservation->getReservationPlaces() as $rp) {
                $entityManager->remove($rp);
            }
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        $this->addFlash('error', 'Paiement annulé : vos places ont été libérées.');

        return $this->redirectToRoute('app_reservation', ['id' => $seanceId]);
    }

    private function getReservationAutorisee(
        int $id,
        ReservationRepository $reservationRepository
    ): Reservation {
        $reservation = $reservationRepository->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        if ($reservation->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $reservation;
    }
}