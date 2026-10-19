<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FilmRepository;
use Doctrine\DBAL\Exception as DatabaseException;
use Psr\Log\LoggerInterface;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function index(
        FilmRepository $filmRepository,
        LoggerInterface $logger
    ): Response {
        try {
            // Récupérer les films depuis la base de données
            $films = $filmRepository->findAll();

            return $this->render('accueil.html.twig', [
                'films' => $films,
            ]);

        } catch (DatabaseException $e) {

            // Enregistrer l'erreur dans les logs
            $logger->error(
                'Erreur lors de l’accès à la base de données.',
                ['exception' => $e]
            );

            // Afficher une page d'erreur propre
            return $this->render(
                'bundles/TwigBundle/Exception/error503.html.twig',
                [],
                new Response('', Response::HTTP_SERVICE_UNAVAILABLE)
            );
        }
    }
}