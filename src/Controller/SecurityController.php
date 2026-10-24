<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $messageErreur = null;

        if ($error !== null) {
            // "Invalid credentials." = mauvais e-mail ou mot de passe
            if ($error instanceof BadCredentialsException) {
                $messageErreur = 'E-mail ou mot de passe incorrect';
            } else {
                // Autres erreurs (Turnstile, compte non vérifié...) : on garde leur message
                $messageErreur = $error->getMessage();
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $messageErreur,
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
}