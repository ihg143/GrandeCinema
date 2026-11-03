<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Service\TurnstileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        TurnstileService $turnstileService,
        EmailVerifier $emailVerifier
    ): Response {
        $user = new Utilisateur();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $token = $request->request->getString(
                'cf-turnstile-response'
            );

            if (!$turnstileService->verify($token)) {
                $this->addFlash(
                    'error',
                    'La vérification de sécurité a échoué. Veuillez réessayer.'
                );

                return $this->redirectToRoute('app_register');
            }

            $plainPassword = $form->get('plainPassword')->getData();

            // Hacher le mot de passe
            $user->setMdp(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            // Le compte reste non vérifié jusqu'au clic sur le lien
            $user->setIsVerified(false);

            // Enregistrer l'utilisateur pour obtenir son identifiant
            $entityManager->persist($user);
            $entityManager->flush();

            // Préparer l'e-mail de confirmation
            $email = (new TemplatedEmail())
                ->from('contact@xn--grandcin-i1a.fr')
                ->to($user->getEmail())
                ->subject('Confirmez votre adresse e-mail - GrandCiné')
                ->htmlTemplate('registration/confirmation_email.html.twig')
                ->context([
                    'prenom' => $user->getPrenom(),
                ]);
                

            try {
                $emailVerifier->sendEmailConfirmation(
                    'app_verify_email',
                    $user,
                    $email
                );
            } catch (TransportExceptionInterface $exception) {
                $this->addFlash(
                    'error',
                    'Votre compte a été créé, mais l’e-mail n’a pas pu être envoyé. Réessayez plus tard.'
                );

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash(
                'success',
                'Votre compte a été créé. Consultez votre boîte mail pour confirmer votre adresse.'
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}




