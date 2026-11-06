<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FilmRepository;
use App\Repository\SeanceRepository;
use Doctrine\DBAL\Exception as DatabaseException;
use Psr\Log\LoggerInterface;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function index(
        FilmRepository $filmRepository,
        SeanceRepository $seanceRepository,
        LoggerInterface $logger
    ): Response {
        try {
            // Récupérer les films depuis la base de données
            $films = $filmRepository->findAll();

            // Récupérer les séances du jour
            $seances = $seanceRepository->createQueryBuilder('s')
                ->join('s.film', 'f')
                ->addSelect('f')
                ->where('s.dateSeance = :date')
                ->setParameter('date', new \DateTime('today'))
                ->orderBy('s.horaireSeance', 'ASC')
                ->getQuery()
                ->getResult();

            return $this->render('accueil.html.twig', [
                'films' => $films,
                'seances' => $seances,
            ]);

        } catch (DatabaseException $e) {

            $logger->error(
                'Erreur lors de l’accès à la base de données.',
                ['exception' => $e]
            );

            
            return $this->render(
                'bundles/TwigBundle/Exception/error503.html.twig',
                [],
                new Response('', Response::HTTP_SERVICE_UNAVAILABLE)
            );
        }
    }
}
