<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Utilisateur;
use App\Repository\PlaceRepository;
use App\Repository\ReservationPlaceRepository;
use App\Repository\SeanceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    #[Route(
        '/reservation/{id}',
        name: 'app_reservation',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function index(
        int $id,
        Request $request,
        SeanceRepository $seanceRepository,
        PlaceRepository $placeRepository,
        ReservationPlaceRepository $reservationPlaceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $seance = $seanceRepository->find($id);

        if (!$seance) {
            throw $this->createNotFoundException('Séance introuvable.');
        }

        if ($seance->getSalle() === null) {
            throw $this->createNotFoundException(
                'Aucune salle n’est associée à cette séance.'
            );
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'reserver_places_' . $id,
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException(
                    'Jeton de sécurité invalide.'
                );
            }

            $utilisateur = $this->getUser();

            if (!$utilisateur instanceof Utilisateur) {
                throw $this->createAccessDeniedException(
                    'Utilisateur non valide.'
                );
            }

            $idsSelectionnes = $request->request->all('places');

            if (empty($idsSelectionnes)) {
                $this->addFlash('error', 'Sélectionnez au moins une place.');

                return $this->redirectToRoute('app_reservation', [
                    'id' => $id,
                ]);
            }

            foreach ($idsSelectionnes as $placeId) {
                if (
                    !is_scalar($placeId)
                    || !ctype_digit((string) $placeId)
                    || (int) $placeId <= 0
                ) {
                    $this->addFlash('error', 'Sélection de places invalide.');

                    return $this->redirectToRoute('app_reservation', [
                        'id' => $id,
                    ]);
                }
            }

            $idsSelectionnes = array_values(array_unique(
                array_map('intval', $idsSelectionnes)
            ));

            $connexion = $entityManager->getConnection();
            $connexion->beginTransaction();

            try {
                // Vérifier les places déjà réservées pour cette séance.
                $reservationsExistantes = $reservationPlaceRepository->findBy([
                    'seance' => $seance,
                ]);

                $idsPlacesReservees = [];

                foreach ($reservationsExistantes as $reservationPlace) {
                    if ($reservationPlace->getPlace() !== null) {
                        $idsPlacesReservees[] = $reservationPlace
                            ->getPlace()
                            ->getIdPlace();
                    }
                }

                // Vérifier les places sélectionnées.
                $placesAReserver = [];

                foreach ($idsSelectionnes as $placeId) {
                    $place = $placeRepository->find($placeId);

                    if (
                        !$place
                        || $place->getSalle() !== $seance->getSalle()
                    ) {
                        throw new \DomainException(
                            'Une des places sélectionnées est invalide.'
                        );
                    }

                    if (in_array($placeId, $idsPlacesReservees, true)) {
                        throw new \DomainException(
                            'Une place vient d’être réservée. Choisissez-en une autre.'
                        );
                    }

                    $placesAReserver[] = $place;
                }

                // Créer la réservation (statut "en attente" par défaut).
                $reservation = new Reservation();
                $reservation->setUtilisateur($utilisateur);
                $reservation->setSeance($seance);

                $entityManager->persist($reservation);
                $entityManager->flush();

                // Associer les places à la réservation.
                foreach ($placesAReserver as $place) {
                    $reservationPlace = new ReservationPlace();
                    $reservationPlace->setPlace($place);
                    $reservationPlace->setSeance($seance);

                    // addReservationPlace() appelle aussi setReservation()
                    $reservation->addReservationPlace($reservationPlace);

                    $entityManager->persist($reservationPlace);
                }

                $entityManager->flush();
                $connexion->commit();

                // On passe au paiement Stripe.
                return $this->redirectToRoute('app_paiement', [
                    'id' => $reservation->getIdReservation(),
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($connexion->isTransactionActive()) {
                    $connexion->rollBack();
                }

                $entityManager->clear();

                $this->addFlash(
                    'error',
                    'Une place vient d’être réservée par un autre utilisateur. Veuillez réessayer.'
                );
            } catch (\DomainException $exception) {
                if ($connexion->isTransactionActive()) {
                    $connexion->rollBack();
                }

                $entityManager->clear();

                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                if ($connexion->isTransactionActive()) {
                    $connexion->rollBack();
                }

                $entityManager->clear();

                throw $exception;
            }

            return $this->redirectToRoute('app_reservation', [
                'id' => $id,
            ]);
        }

        // Afficher les places de la séance.
        $places = $placeRepository->findBy(
            ['salle' => $seance->getSalle()],
            ['numeroPlace' => 'ASC']
        );

        $placesReservees = $reservationPlaceRepository->findBy([
            'seance' => $seance,
        ]);

        $idsPlacesReservees = [];

        foreach ($placesReservees as $reservationPlace) {
            if ($reservationPlace->getPlace() !== null) {
                $idsPlacesReservees[] = $reservationPlace
                    ->getPlace()
                    ->getIdPlace();
            }
        }

        return $this->render('reservation/index.html.twig', [
            'seance' => $seance,
            'places' => $places,
            'idsPlacesReservees' => $idsPlacesReservees,
        ]);
    }
}