<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'turnstile_site_key' => $this->getParameter('turnstile_site_key'),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'Cette méthode est interceptée par le firewall Symfony.'
        );
    }

    #[Route('/test-password', name: 'app_test_password')]
    public function testPassword(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'test@cinemax.fr']);

        if (!$user) {
            return new Response('Utilisateur introuvable');
        }

        if ($passwordHasher->isPasswordValid($user, 'bhim123')) {
            return new Response('Mot de passe valide');
        }

        return new Response('Mot de passe invalide');
    }
}
