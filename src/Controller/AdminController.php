<?php

namespace App\Controller;

use App\Entity\Film;
use App\Entity\Seance;
use App\Repository\PlaceRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        EntityManagerInterface $entityManager,
        PlaceRepository $placeRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $filmRepository = $entityManager->getRepository(Film::class);
        $seanceRepository = $entityManager->getRepository(Seance::class);

        $nbFilms = $filmRepository->count([]);

        $seances = $seanceRepository->findBy(
            [],
            ['dateSeance' => 'ASC', 'horaireSeance' => 'ASC']
        );

        $maintenant = new \DateTimeImmutable();
        $aujourdhui = $maintenant->format('Y-m-d');
        $seancesAujourdhui = 0;

        $totalPlacesVendues = 0;
        $totalCapacite = 0;

        $parFilm = [];
        $parJour = [];

        foreach ($seances as $seance) {
            $dateSeance = $seance->getDateSeance();
            $horaireSeance = $seance->getHoraireSeance();

            if (!$dateSeance || !$horaireSeance) {
                continue;
            }

            $jourCle = $dateSeance->format('Y-m-d');

            if ($jourCle === $aujourdhui) {
                $seancesAujourdhui++;
            }
            $debutSeance = new \DateTimeImmutable(
                $jourCle . ' ' . $horaireSeance->format('H:i:s')
            );

            $film = $seance->getFilm();
            $duree = $film?->getDuree() ?? 0;

            if ($duree <= 0) {
                continue;
            }

            $finSeance = $debutSeance->modify('+' . $duree . ' minutes');

            if ($finSeance > $maintenant) {
                continue;
            }

            $salle = $seance->getSalle();

            $capacite = $salle !== null
                ? $placeRepository->count(['salle' => $salle])
                : 0;

            $vendues = 0;

            foreach ($seance->getReservations() as $reservation) {
                if ($reservation->isPayee()) {
                    $vendues += $reservation->getReservationPlaces()->count();
                }
            }

            $totalPlacesVendues += $vendues;
            $totalCapacite += $capacite;

            $nomFilm = $film?->getNomFilm() ?? 'Film inconnu';

            if (!isset($parFilm[$nomFilm])) {
                $parFilm[$nomFilm] = [
                    'vendues' => 0,
                    'capacite' => 0,
                ];
            }

            $parFilm[$nomFilm]['vendues'] += $vendues;
            $parFilm[$nomFilm]['capacite'] += $capacite;

            $parJour[$jourCle] = ($parJour[$jourCle] ?? 0) + $vendues;
        }

        // Taux global calculé uniquement sur les séances terminées.
        $tauxGlobal = $totalCapacite > 0
            ? (int) round(($totalPlacesVendues / $totalCapacite) * 100)
            : 0;

        $tauxGlobal = min(100, max(0, $tauxGlobal));

        // Taux de remplissage par film, sur les séances terminées.
        $remplissage = [];

        foreach ($parFilm as $nomFilm => $donnees) {
            $taux = $donnees['capacite'] > 0
                ? (int) round(
                    ($donnees['vendues'] / $donnees['capacite']) * 100
                )
                : 0;

            $remplissage[] = [
                'film' => $nomFilm,
                'taux' => min(100, max(0, $taux)),
            ];
        }

        // Affluence : les sept derniers jours ayant des séances terminées.
        ksort($parJour);
        $parJour = array_slice($parJour, -7, null, true);

        $affluence = [];

        foreach ($parJour as $jour => $valeur) {
            $affluence[] = [
                'jour' => (new \DateTimeImmutable($jour))->format('d/m'),
                'valeur' => $valeur,
                'festival' => false,
            ];
        }

        $maxAffluence = !empty($affluence)
            ? max(array_column($affluence, 'valeur'))
            : 0;

        // Dix dernières réservations.
        $dernieres = $reservationRepository->findBy(
            [],
            ['idReservation' => 'DESC'],
            10
        );

        $dernieresReservations = [];

        foreach ($dernieres as $resa) {
            $seance = $resa->getSeance();
            $client = $resa->getUtilisateur();

            $dateReservation = '-';

            if ($seance && $seance->getDateSeance()) {
                $dateReservation = $seance->getDateSeance()->format('d/m/Y');

                if ($seance->getHoraireSeance()) {
                    $dateReservation .= ' à '
                        . $seance->getHoraireSeance()->format('H:i');
                }
            }

            $dernieresReservations[] = [
                'client' => $client
                    ? trim($client->getPrenom() . ' ' . $client->getNom())
                    : 'Inconnu',
                'film' => $seance?->getFilm()?->getNomFilm() ?? '-',
                'places' => $resa->getReservationPlaces()->count(),
                'date' => $dateReservation,
                'statut' => $resa->isPayee() ? 'Payée' : 'En attente',
            ];
        }

        $stats = [
            'nbFilms' => $nbFilms,
            'nbSeances' => count($seances),
            'seancesAujourdhui' => $seancesAujourdhui,
            'nbReservations' => $reservationRepository->count([
                'statut' => 'payee',
            ]),
            'tauxRemplissage' => $tauxGlobal,
        ];

        return $this->render('admin/index.html.twig', [
            'stats' => $stats,
            'remplissage' => $remplissage,
            'affluence' => $affluence,
            'maxAffluence' => $maxAffluence,
            'dernieresReservations' => $dernieresReservations,
        ]);
    }
}
