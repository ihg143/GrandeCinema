<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\TurnstileService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class TurnstileLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private TurnstileService $turnstileService,
        private UtilisateurRepository $utilisateurRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_login'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->request->getString('cf-turnstile-response');

        if (!$this->turnstileService->verify($token)) {
            throw new CustomUserMessageAuthenticationException(
                'La vérification de sécurité a échoué. Veuillez réessayer.'
            );
        }

        $email = $request->request->getString('_username');
        $password = $request->request->getString('_password');
        $csrfToken = $request->request->getString('_csrf_token');

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): Utilisateur {
                $user = $this->utilisateurRepository->findOneBy([
                    'email' => $userIdentifier,
                ]);

                if (!$user instanceof Utilisateur) {
                    throw new CustomUserMessageAuthenticationException(
                        'Adresse e-mail ou mot de passe incorrect'
                    );
                }

                if (!$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException(
                        'Veuillez confirmer votre adresse e-mail avant de vous connecter.'
                    );
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_accueil')
        );
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        $request->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            $exception
        );

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            $request->request->getString('_username')
        );

        return new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
    }

    /**
     * Appelé quand un utilisateur non connecté accède à une page protégée :
     * on le redirige vers la page de connexion au lieu d'afficher une erreur 401.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
    }
}