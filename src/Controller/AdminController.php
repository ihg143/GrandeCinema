<?php

namespace App\Controller;

use App\Entity\Film;
use App\Entity\Seance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer les films et les séances depuis la base de données
        $films = $entityManager->getRepository(Film::class)->findAll();

        $seances = $entityManager->getRepository(Seance::class)->findBy(
            [],
            ['dateSeance' => 'ASC', 'horaireSeance' => 'ASC']
        );

        // Compter les séances d'aujourd'hui
        $aujourdhui = (new \DateTime())->format('Y-m-d');
        $seancesAujourdhui = 0;

        foreach ($seances as $seance) {
            if ($seance->getDateSeance()->format('Y-m-d') === $aujourdhui) {
                $seancesAujourdhui++;
            }
        }

        // Compter les réservations réelles
        $nbReservations = 0;

        foreach ($seances as $seance) {
            $nbReservations += $seance->getReservations()->count();
        }

        // Statistiques
        $stats = [
            'nbFilms' => count($films),
            'nbSeances' => count($seances),
            'seancesAujourdhui' => $seancesAujourdhui,
            'nbReservations' => $nbReservations,
            'tauxRemplissage' => 0,
        ];

        // Taux de remplissage par film : pas encore calculé
        $remplissage = [];

        // Affluence : pas encore calculée
        $affluence = [];

        // Dernières réservations : pas encore récupérées
        $dernieresReservations = [];

        return $this->render('admin/index.html.twig', [
            'films' => $films,
            'seances' => $seances,
            'stats' => $stats,
            'remplissage' => $remplissage,
            'affluence' => $affluence,
            'maxAffluence' => 0,
            'dernieresReservations' => $dernieresReservations,
        ]);
    }
}
