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
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        EntityManagerInterface $entityManager,
        PlaceRepository $placeRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $films = $entityManager->getRepository(Film::class)->findAll();

        $seances = $entityManager->getRepository(Seance::class)->findBy(
            [],
            ['dateSeance' => 'ASC', 'horaireSeance' => 'ASC']
        );

        $aujourdhui = (new \DateTime())->format('Y-m-d');
        $seancesAujourdhui = 0;

        $nbReservationsPayees = 0;
        $totalPlacesVendues = 0;
        $totalCapacite = 0;

        $parFilm = [];  // [nomFilm => ['vendues' => x, 'capacite' => y]]
        $parJour = [];  // [Y-m-d => nombre de spectateurs]

        foreach ($seances as $seance) {
            $jourCle = $seance->getDateSeance()->format('Y-m-d');

            if ($jourCle === $aujourdhui) {
                $seancesAujourdhui++;
            }

            // Capacité = nombre de places de la salle (salle = simple entier)
            $salle = $seance->getSalle();
            $capacite = $salle !== null
                ? $placeRepository->count(['salle' => $salle])
                : 0;

            // Places vendues = places des réservations PAYÉES uniquement
            $vendues = 0;

            foreach ($seance->getReservations() as $reservation) {
                if ($reservation->isPayee()) {
                    $nbReservationsPayees++;
                    $vendues += $reservation->getReservationPlaces()->count();
                }
            }

            $totalPlacesVendues += $vendues;
            $totalCapacite += $capacite;

            $nomFilm = $seance->getFilm()?->getNomFilm() ?? 'Film inconnu';

            if (!isset($parFilm[$nomFilm])) {
                $parFilm[$nomFilm] = ['vendues' => 0, 'capacite' => 0];
            }

            $parFilm[$nomFilm]['vendues'] += $vendues;
            $parFilm[$nomFilm]['capacite'] += $capacite;

            $parJour[$jourCle] = ($parJour[$jourCle] ?? 0) + $vendues;
        }

        // Taux de remplissage global
        $tauxGlobal = $totalCapacite > 0
            ? (int) round($totalPlacesVendues / $totalCapacite * 100)
            : 0;

        // Taux de remplissage par film
        $remplissage = [];

        foreach ($parFilm as $nomFilm => $donnees) {
            $remplissage[] = [
                'film' => $nomFilm,
                'taux' => $donnees['capacite'] > 0
                    ? (int) round($donnees['vendues'] / $donnees['capacite'] * 100)
                    : 0,
            ];
        }

        // Affluence : les 7 derniers jours avec des séances
        ksort($parJour);
        $parJour = array_slice($parJour, -7, null, true);

        $affluence = [];

        foreach ($parJour as $jour => $valeur) {
            $affluence[] = [
                'jour' => (new \DateTime($jour))->format('d/m'),
                'valeur' => $valeur,
                'festival' => false,
            ];
        }

        $maxAffluence = $affluence === []
            ? 0
            : max(array_column($affluence, 'valeur'));

        // 10 dernières réservations (payées ou non, avec leur statut)
        $dernieres = $reservationRepository->findBy(
            [],
            ['idReservation' => 'DESC'],
            10
        );

        $dernieresReservations = [];

        foreach ($dernieres as $resa) {
            $seance = $resa->getSeance();
            $client = $resa->getUtilisateur();

            $dernieresReservations[] = [
                'client' => $client
                    ? $client->getPrenom() . ' ' . $client->getNom()
                    : 'Inconnu',
                'film' => $seance?->getFilm()?->getNomFilm() ?? '-',
                'places' => $resa->getReservationPlaces()->count(),
                'date' => $seance
                    ? $seance->getDateSeance()->format('d/m/Y')
                        . ' à ' . $seance->getHoraireSeance()->format('H:i')
                    : '-',
                'statut' => $resa->isPayee() ? 'Payée' : 'En attente',
            ];
        }

        $stats = [
            'nbFilms' => count($films),
            'nbSeances' => count($seances),
            'seancesAujourdhui' => $seancesAujourdhui,
            'nbReservations' => $nbReservationsPayees,
            'tauxRemplissage' => $tauxGlobal,
        ];

        return $this->render('admin/index.html.twig', [
            'films' => $films,
            'seances' => $seances,
            'stats' => $stats,
            'remplissage' => $remplissage,
            'affluence' => $affluence,
            'maxAffluence' => $maxAffluence,
            'dernieresReservations' => $dernieresReservations,
        ]);
    }
}