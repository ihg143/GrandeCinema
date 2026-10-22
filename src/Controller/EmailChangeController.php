<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Doctrine\ORM\EntityManagerInterface;

final class EmailChangeController extends AbstractController
{
    #[Route(
        '/profil/confirmer-email',
        name: 'app_confirm_change_email',
        methods: ['GET']
    )]
    public function confirmEmailChange(
        Request $request,
        EmailVerifier $emailVerifier,
        EntityManagerInterface $entityManager
    ): Response {
        $id = $request->query->get('id');

        if (!$id || !ctype_digit((string) $id)) {
            $this->addFlash(
                'error',
                'Le lien de confirmation est invalide.'
            );

            return $this->redirectToRoute('app_profil');
        }

        $user = $entityManager
            ->getRepository(Utilisateur::class)
            ->find((int) $id);

        if (!$user) {
            throw $this->createNotFoundException(
                'Utilisateur introuvable.'
            );
        }

        if ($user->getEmailEnAttente() === null) {
            $this->addFlash(
                'error',
                'Aucune nouvelle adresse e-mail n’est en attente.'
            );

            return $this->redirectToRoute('app_profil');
        }

        try {
            $emailVerifier->handleEmailChangeConfirmation(
                $request,
                $user
            );

            $this->addFlash(
                'success',
                'Votre nouvelle adresse e-mail a été confirmée.'
            );

            return $this->redirectToRoute('app_login');
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'error',
                'Le lien de confirmation est invalide ou expiré.'
            );

            return $this->redirectToRoute('app_profil');
        }
    }
}