<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager
    ) {
    }

    // Confirmation de l'adresse e-mail lors de l'inscription
    public function sendEmailConfirmation(
        string $routeName,
        Utilisateur $user,
        TemplatedEmail $email
    ): void {
        $signature = $this->verifyEmailHelper->generateSignature(
            $routeName,
            (string) $user->getIdUser(),
            (string) $user->getEmail(),
            ['id' => $user->getIdUser()]
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signature->getSignedUrl();
        $context['expiresAtMessageKey'] = $signature->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signature->getExpirationMessageData();

        $email->context($context);

        $this->mailer->send($email);
    }

    // Validation de l'adresse e-mail lors de l'inscription
    public function handleEmailConfirmation(
        Request $request,
        Utilisateur $user
    ): void {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getIdUser(),
            (string) $user->getEmail()
        );

        $user->setIsVerified(true);

        $this->entityManager->flush();
    }

    // Envoi du lien de confirmation de la nouvelle adresse
    public function sendEmailChangeConfirmation(
        string $routeName,
        Utilisateur $user,
        TemplatedEmail $email
    ): void {
        $pendingEmail = $user->getEmailEnAttente();

        if ($pendingEmail === null || $pendingEmail === '') {
            throw new \LogicException(
                'Aucune nouvelle adresse e-mail n’est en attente de confirmation.'
            );
        }

        $signature = $this->verifyEmailHelper->generateSignature(
            $routeName,
            (string) $user->getIdUser(),
            $pendingEmail,
            ['id' => $user->getIdUser()]
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signature->getSignedUrl();
        $context['expiresAtMessageKey'] = $signature->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signature->getExpirationMessageData();

        $email->context($context);

        $this->mailer->send($email);
    }

    // Confirmation et remplacement de l'adresse e-mail
    public function handleEmailChangeConfirmation(
        Request $request,
        Utilisateur $user
    ): void {
        $pendingEmail = $user->getEmailEnAttente();

        if ($pendingEmail === null || $pendingEmail === '') {
            throw new \LogicException(
                'Aucune nouvelle adresse e-mail n’est en attente de confirmation.'
            );
        }

        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getIdUser(),
            $pendingEmail
        );

        $user->setEmail($pendingEmail);
        $user->setEmailEnAttente(null);

        $this->entityManager->flush();
    }
}