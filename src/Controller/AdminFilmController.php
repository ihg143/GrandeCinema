<?php

namespace App\Controller;

use App\Entity\Film;
use App\Form\FilmType;
use App\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/film')]
final class AdminFilmController extends AbstractController
{
    #[Route('', name: 'admin_film_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FilmRepository $filmRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string) $request->query->get('q', ''));
        $genreSelectionne = trim((string) $request->query->get('genre', ''));
        $dateSortie = trim((string) $request->query->get('dateSortie', ''));

        $genres = $this->getGenres($filmRepository);

        $queryBuilder = $filmRepository->createQueryBuilder('f');

        if ($q !== '') {
            $queryBuilder
                ->andWhere('LOWER(f.nomFilm) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($genreSelectionne !== '') {
            $queryBuilder
                ->andWhere('f.genre = :genre')
                ->setParameter('genre', $genreSelectionne);
        }

        if ($dateSortie !== '') {
            $dateValide = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateSortie
            );

            $erreursDate = \DateTimeImmutable::getLastErrors();

            if (
                $dateValide !== false
                && (
                    $erreursDate === false
                    || (
                        $erreursDate['warning_count'] === 0
                        && $erreursDate['error_count'] === 0
                    )
                )
            ) {
                $queryBuilder
                    ->andWhere('f.dateSortie = :dateSortie')
                    ->setParameter('dateSortie', $dateValide);
            } else {
                $dateSortie = '';
            }
        }

        $films = $queryBuilder
            ->orderBy('f.nomFilm', 'ASC')
            ->getQuery()
            ->getResult();

        $film = new Film();
        $form = $this->createForm(FilmType::class, $film);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFilm')->getData();

            if ($imageFile) {
                $extension = $imageFile->guessExtension() ?: 'bin';
                $nouveauNom = bin2hex(random_bytes(16)) . '.' . $extension;

                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/image',
                    $nouveauNom
                );

                $film->setImageFilm($nouveauNom);
            }

            $entityManager->persist($film);
            $entityManager->flush();

            $this->addFlash('success', 'Le film a été ajouté avec succès.');

            return $this->redirectToRoute('admin_film_index');
        }

        return $this->render('admin/gererFilm.html.twig', [
            'films' => $films,
            'form' => $form,
            'isEdit' => false,
            'genres' => $genres,
            'filtres' => [
                'q' => $q,
                'genre' => $genreSelectionne,
                'dateSortie' => $dateSortie,
            ],
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_film_edit', methods: ['GET', 'POST'])]
    public function edit(
        Film $film,
        Request $request,
        FilmRepository $filmRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(FilmType::class, $film);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFilm')->getData();

            if ($imageFile) {
                $extension = $imageFile->guessExtension() ?: 'bin';
                $nouveauNom = bin2hex(random_bytes(16)) . '.' . $extension;

                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/image',
                    $nouveauNom
                );

                $film->setImageFilm($nouveauNom);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le film a été modifié avec succès.');

            return $this->redirectToRoute('admin_film_index');
        }

        return $this->render('admin/gererFilm.html.twig', [
            'films' => $filmRepository->findBy([], ['nomFilm' => 'ASC']),
            'form' => $form,
            'isEdit' => true,
            'genres' => $this->getGenres($filmRepository),
            'filtres' => [
                'q' => '',
                'genre' => '',
                'dateSortie' => '',
            ],
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_film_delete', methods: ['POST'])]
    public function delete(
        Film $film,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid(
            'delete' . $film->getIdFilm(),
            $request->request->get('_token')
        )) {
            $entityManager->remove($film);
            $entityManager->flush();

            $this->addFlash('success', 'Le film a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_film_index');
    }

    /**
     * Récupère la liste unique des genres renseignés.
     */
    private function getGenres(FilmRepository $filmRepository): array
    {
        $genres = [];

        foreach ($filmRepository->findAll() as $film) {
            $genre = trim((string) $film->getGenre());

            if ($genre !== '') {
                $genres[$genre] = $genre;
            }
        }

        $genres = array_values($genres);
        sort($genres, SORT_NATURAL | SORT_FLAG_CASE);

        return $genres;
    }
}
