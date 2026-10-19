<?php

namespace App\Controller;

use App\Repository\FilmRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SeanceRepository;
class FilmController extends AbstractController
{
    #[Route('/film', name: 'app_film')]
    public function index(
        FilmRepository $filmRepository,
        Request $request
    ): Response {
        $q = $request->query->get('q', '');

        if ($q !== '') {
            $films = $filmRepository->createQueryBuilder('f')
                ->where('f.nomFilm LIKE :q')
                ->setParameter('q', '%' . $q . '%')
                ->orderBy('f.nomFilm', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            $films = $filmRepository->findAll();
        }

        return $this->render('film.html.twig', [
            'films' => $films,
            'q' => $q,
        ]);
    }

    #[Route('/film/{id}', name: 'app_film_detail', requirements: ['id' => '\d+'])]
    public function detail(
        int $id,
        FilmRepository $filmRepository,
        SeanceRepository $seanceRepository
    ): Response {
        $film = $filmRepository->find($id);

        if (!$film) {
            throw $this->createNotFoundException('Film introuvable.');
        }

        $seances = $seanceRepository->findBy(
            ['film' => $film],
            ['dateSeance' => 'ASC', 'horaireSeance' => 'ASC']
        );

        return $this->render('film/detail.html.twig', [
            'film' => $film,
            'seances' => $seances,
        ]);
     }
}