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
    // Afficher les films et ajouter un film
    #[Route('', name: 'admin_film_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FilmRepository $filmRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $film = new Film();

        $form = $this->createForm(FilmType::class, $film);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFilm')->getData();

            if ($imageFile) {
                $nouveauNom = bin2hex(random_bytes(16))
                    . '.' . $imageFile->guessExtension();

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
            'films' => $filmRepository->findAll(),
            'form' => $form,
            'isEdit' => false,
        ]);
    }

    // Modifier un film
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
                $nouveauNom = bin2hex(random_bytes(16))
                    . '.' . $imageFile->guessExtension();

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
            'films' => $filmRepository->findAll(),
            'form' => $form,
            'isEdit' => true,
        ]);
    }

    // Supprimer un film
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
}