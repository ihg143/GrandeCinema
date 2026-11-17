<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Utilisateur;
use App\Repository\PlaceRepository;
use App\Repository\ReservationPlaceRepository;
use App\Repository\SeanceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationApiController extends AbstractController
{
    #[Route(
        '/api/reservations',
        name: 'api_reservation_create',
        methods: ['POST']
    )]
    #[OA\Post(
        path: '/api/reservations',
        summary: 'Créer une réservation',
        description: 'Permet à un utilisateur connecté de réserver des places pour une séance.',
        tags: ['Réservations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['idSeance', 'places'],
                properties: [
                    new OA\Property(
                        property: 'idSeance',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'places',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [12, 13]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Réservation créée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Réservation créée avec succès.'
                        ),
                        new OA\Property(
                            property: 'idReservation',
                            type: 'integer',
                            example: 42
                        ),
                        new OA\Property(
                            property: 'statut',
                            type: 'string',
                            example: 'en_attente'
                        ),
                        new OA\Property(
                            property: 'nombrePlaces',
                            type: 'integer',
                            example: 2
                        ),
                        new OA\Property(
                            property: 'total',
                            type: 'integer',
                            example: 20
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides, place incorrecte ou déjà réservée.'
            ),
            new OA\Response(
                response: 403,
                description: 'Utilisateur non autorisé.'
            ),
            new OA\Response(
                response: 404,
                description: 'Séance introuvable.'
            ),
            new OA\Response(
                response: 409,
                description: 'Conflit : une place vient d’être réservée.'
            )
        ]
    )]
    public function create(
        Request $request,
        SeanceRepository $seanceRepository,
        PlaceRepository $placeRepository,
        ReservationPlaceRepository $reservationPlaceRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        // Vérifier l'authentification
        $this->denyAccessUnlessGranted('ROLE_USER');

        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            return $this->json([
                'message' => 'Utilisateur non valide.',
            ], 403);
        }

        // Récupérer les données JSON
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json([
                'message' => 'Le JSON envoyé est invalide.',
            ], 400);
        }

        $idSeance = $data['idSeance'] ?? null;
        $idsPlaces = $data['places'] ?? null;

        // Vérifier les données reçues
        if (
            !is_int($idSeance)
            || $idSeance <= 0
            || !is_array($idsPlaces)
            || empty($idsPlaces)
        ) {
            return $this->json([
                'message' => 'Les données envoyées sont invalides.',
                'exemple' => [
                    'idSeance' => 1,
                    'places' => [12, 13],
                ],
            ], 400);
        }

        // Vérifier les identifiants des places
        foreach ($idsPlaces as $placeId) {
            if (!is_int($placeId) || $placeId <= 0) {
                return $this->json([
                    'message' => 'La liste des places est invalide.',
                ], 400);
            }
        }

        // Refuser les places en double
        $idsPlaces = array_values(array_unique($idsPlaces));

        if (count($idsPlaces) !== count($data['places'])) {
            return $this->json([
                'message' => 'La liste contient des places en double.',
            ], 400);
        }

        // Vérifier que la séance existe
        $seance = $seanceRepository->find($idSeance);

        if (!$seance) {
            return $this->json([
                'message' => 'Séance introuvable.',
            ], 404);
        }

        if ($seance->getSalle() === null) {
            return $this->json([
                'message' => 'Aucune salle associée à cette séance.',
            ], 400);
        }

        $connexion = $entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            // Récupérer les places déjà réservées
            $reservationsExistantes = $reservationPlaceRepository->findBy([
                'seance' => $seance,
            ]);

            $idsPlacesReservees = [];

            foreach ($reservationsExistantes as $reservationPlace) {
                $place = $reservationPlace->getPlace();

                if ($place !== null) {
                    $idsPlacesReservees[] = $place->getIdPlace();
                }
            }

            // Vérifier les places sélectionnées
            $placesAReserver = [];

            foreach ($idsPlaces as $placeId) {
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
                        'Une des places est déjà réservée.'
                    );
                }

                $placesAReserver[] = $place;
            }

            // Créer la réservation
            $reservation = new Reservation();
            $reservation->setUtilisateur($utilisateur);
            $reservation->setSeance($seance);

            $entityManager->persist($reservation);
            $entityManager->flush();

            // Associer les places à la réservation
            foreach ($placesAReserver as $place) {
                $reservationPlace = new ReservationPlace();
                $reservationPlace->setPlace($place);
                $reservationPlace->setSeance($seance);

                $reservation->addReservationPlace($reservationPlace);

                $entityManager->persist($reservationPlace);
            }

            $entityManager->flush();
            $connexion->commit();

            // Retourner la réponse JSON
            return $this->json([
                'message' => 'Réservation créée avec succès.',
                'idReservation' => $reservation->getIdReservation(),
                'statut' => $reservation->getStatut(),
                'nombrePlaces' => count($placesAReserver),
                'total' => $reservation->getTotal(),
            ], 201);

        } catch (UniqueConstraintViolationException) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            $entityManager->clear();

            return $this->json([
                'message' => 'Une place vient d’être réservée. Veuillez réessayer.',
            ], 409);

        } catch (\DomainException $exception) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            $entityManager->clear();

            return $this->json([
                'message' => $exception->getMessage(),
            ], 400);

        } catch (\Throwable $exception) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            $entityManager->clear();

            throw $exception;
        }
    }
}
