<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ReservationPlaceRepository;
use App\Repository\ReservationRepository;
use App\Repository\UtilisateurRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\Session;

final class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'app_profil', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UtilisateurRepository $utilisateurRepository,
        EmailVerifier $emailVerifier,
        ReservationRepository $reservationRepository,
        ReservationPlaceRepository $reservationPlaceRepository
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // Modification des informations personnelles
        if ($request->request->has('update_profile')) {
            $nom = trim((string) $request->request->get('nom'));
            $prenom = trim((string) $request->request->get('prenom'));
            $nouvelEmail = trim((string) $request->request->get('email'));

            if ($nom === '' || $prenom === '') {
                $this->addFlash('error', 'Le nom et le prénom sont obligatoires.');

                return $this->redirectToRoute('app_profil');
            }

            if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez saisir une adresse e-mail valide.');

                return $this->redirectToRoute('app_profil');
            }

            $user->setNom($nom);
            $user->setPrenom($prenom);

            // Si l'adresse est différente, on demande une confirmation
            if ($nouvelEmail !== $user->getEmail()) {
                $existingUser = $utilisateurRepository->findOneBy([
                    'email' => $nouvelEmail,
                ]);

                if ($existingUser !== null && $existingUser->getIdUser() !== $user->getIdUser()) {
                    $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée.');

                    return $this->redirectToRoute('app_profil');
                }

                $user->setEmailEnAttente($nouvelEmail);

                $entityManager->flush();

                try {
                    $email = (new TemplatedEmail())
                        ->from('confirmation@grandcine.test')
                        ->to($nouvelEmail)
                        ->subject('Confirmez votre nouvelle adresse e-mail - GrandCiné')
                        ->htmlTemplate('registration/confirmation_email_change.html.twig')
                        ->context([
                            'user' => $user,
                            'newEmail' => $nouvelEmail,
                        ]);

                    $emailVerifier->sendEmailChangeConfirmation(
                        'app_confirm_change_email',
                        $user,
                        $email
                    );

                    $this->addFlash(
                        'success',
                        'Un lien de confirmation a été envoyé à votre nouvelle adresse e-mail. Votre ancienne adresse reste active jusqu’à la confirmation.'
                    );
                } catch (\Throwable $exception) {
                    $this->addFlash(
                        'error',
                        'Impossible d’envoyer l’e-mail de confirmation. Vérifiez la configuration de Mailtrap.'
                    );
                }
            } else {
                $entityManager->flush();

                $this->addFlash('success', 'Votre profil a été mis à jour.');
            }

            return $this->redirectToRoute('app_profil');
        }

        // Modification du mot de passe
        if ($request->request->has('update_password')) {
            $currentPassword = (string) $request->request->get('current_password');
            $newPassword = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            } elseif ($newPassword === '') {
                $this->addFlash('error', 'Le nouveau mot de passe ne peut pas être vide.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            } else {
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);

                $user->setMdp($hashedPassword);

                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe mis à jour avec succès !');
            }

            return $this->redirectToRoute('app_profil');
        }

        // Chargement des réservations de l'utilisateur et de leurs places
        $reservations = $reservationRepository->findBy([
            'utilisateur' => $user,
        ]);

        $reservationsDetails = [];

        foreach ($reservations as $reservation) {
            $reservationsDetails[] = [
                'reservation' => $reservation,
                'places' => $reservationPlaceRepository->findBy([
                    'reservation' => $reservation,
                ]),
            ];
        }

        return $this->render('profil/index.html.twig', [
            'reservationsDetails' => $reservationsDetails,
        ]);
    }

    // Suppression du compte
    #[Route('/profil/supprimer-compte', name: 'app_supprimer_compte', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function supprimerCompte(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // Vérification du jeton CSRF
        if (!$this->isCsrfTokenValid(
            'delete_account',
            $request->request->get('_token')
        )) {
            $this->addFlash('error', 'La demande de suppression est invalide.');

            return $this->redirectToRoute('app_profil');
        }

        // Vérification du mot de passe actuel
        $currentPassword = (string) $request->request->get('current_password');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');

            return $this->redirectToRoute('app_profil');
        }

        // Anonymisation des informations personnelles
        $userId = $user->getIdUser();

        $user->setNom('Compte supprimé');
        $user->setPrenom('Utilisateur');
        $user->setEmail('compte-supprime-' . $userId . '@example.invalid');
        $user->setEmailEnAttente(null);

        // Remplacement du mot de passe par une valeur aléatoire
        $user->setMdp(
            $passwordHasher->hashPassword(
                $user,
                bin2hex(random_bytes(32))
            )
        );

        $user->setIsVerified(false);

        $entityManager->flush();

        // Déconnexion de l'utilisateur
        $tokenStorage->setToken(null);
        /** @var Session $session */
        $session = $request->getSession(); 
        $request->getSession()->invalidate();
        // Message de confirmation
    $session->getFlashBag()->add(
    'account_deleted',
    'Votre compte a été supprimé avec succès.'
);


        return $this->redirectToRoute('app_accueil');
    }
}
