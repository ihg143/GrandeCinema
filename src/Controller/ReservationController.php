<?php

namespace App\Controller;

use App\Repository\SeanceRepository;
use App\Repository\PlaceRepository;
use App\Repository\ReservationPlaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    #[Route('/reservation/{id}', name: 'app_reservation', requirements: ['id' => '\d+'])]
    public function index(
        int $id,
        SeanceRepository $seanceRepository,
        PlaceRepository $placeRepository,
        ReservationPlaceRepository $reservationPlaceRepository
    ): Response {
         // Seuls les utilisateurs connectés peuvent réserver
    $this->denyAccessUnlessGranted('ROLE_USER');
        // Récupérer la séance
        $seance = $seanceRepository->find($id);

        if (!$seance) {
            throw $this->createNotFoundException('Séance introuvable.');
        }

        // Vérifier que la séance possède une salle
        if ($seance->getSalle() === null) {
            throw $this->createNotFoundException(
                'Aucune salle n’est associée à cette séance.'
            );
        }

        // Récupérer les places de la salle
        $places = $placeRepository->findBy(
            ['salle' => $seance->getSalle()],
            ['numeroPlace' => 'ASC']
        );

        // Récupérer les places déjà réservées pour cette séance
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
