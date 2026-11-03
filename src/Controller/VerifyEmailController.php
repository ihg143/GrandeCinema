<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class VerifyEmailController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EmailVerifier $emailVerifier
    ): Response {
        $id = $request->query->get('id');

        if ($id === null) {
            $this->addFlash('error', 'Lien de confirmation invalide.');

            return $this->redirectToRoute('app_register');
        }

        $user = $utilisateurRepository->find($id);

        if (!$user instanceof Utilisateur) {
            $this->addFlash('error', 'Utilisateur introuvable.');

            return $this->redirectToRoute('app_register');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'error',
                'Le lien de confirmation est invalide ou expiré.'
            );

            return $this->redirectToRoute('app_register');
        }

        // Signaler à la page de connexion que l'e-mail est confirmé.
        $request->getSession()->set('email_confirmed', true);

        // Afficher la page de succès dans l'onglet de confirmation.
        return $this->render('registration/email_confirmed.html.twig');
    }

    #[Route('/verify/email/status', name: 'app_verify_email_status')]
    public function emailConfirmationStatus(Request $request): Response
    {
        $confirmed = $request->getSession()->get('email_confirmed', false);

        if ($confirmed) {
            $request->getSession()->remove('email_confirmed');
        }

        return $this->json([
            'confirmed' => $confirmed,
            'message' => 'Votre adresse e-mail est confirmée. Vous pouvez maintenant vous connecter.',
        ]);
    }
}