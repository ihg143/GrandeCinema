<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class VerifyEmailControllerTest extends WebTestCase
{
    private function creerUtilisateur(): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('verify_email_') . '@example.com');
        $user->setMdp('password_test');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function supprimerUtilisateur(int $id): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $em->getRepository(Utilisateur::class)->find($id);

        if ($user !== null) {
            $em->remove($user);
            $em->flush();
        }
    }

    public function testVerificationWithoutIdRedirectsToRegister(): void
    {
        $client = static::createClient();

        $client->request('GET', '/verify/email');

        self::assertResponseRedirects('/register');
    }

    public function testVerificationWithUnknownUserRedirectsToRegister(): void
    {
        $client = static::createClient();

        $repository = $this->createMock(UtilisateurRepository::class);

        $repository->expects(self::once())
            ->method('find')
            ->with('999999')
            ->willReturn(null);

        static::getContainer()->set(
            UtilisateurRepository::class,
            $repository
        );

        $client->request('GET', '/verify/email?id=999999');

        self::assertResponseRedirects('/register');
    }

    public function testVerificationWithInvalidLinkRedirectsToRegister(): void
    {
        $client = static::createClient();

        $user = $this->creerUtilisateur();
        $userId = $user->getIdUser();

        $exception = $this->createMock(
            VerifyEmailExceptionInterface::class
        );

        $emailVerifier = $this->createMock(EmailVerifier::class);

        $emailVerifier->expects(self::once())
            ->method('handleEmailConfirmation')
            ->willThrowException($exception);

        static::getContainer()->set(
            EmailVerifier::class,
            $emailVerifier
        );

        try {
            $client->request(
                'GET',
                '/verify/email?id=' . $userId
            );

            self::assertResponseRedirects('/register');
        } finally {
            $this->supprimerUtilisateur($userId);
        }
    }

    public function testVerificationWithValidLinkDisplaysConfirmationPage(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $user = $this->creerUtilisateur();
        $userId = $user->getIdUser();

        $emailVerifier = $this->createMock(EmailVerifier::class);

        $emailVerifier->expects(self::once())
            ->method('handleEmailConfirmation');

        static::getContainer()->set(
            EmailVerifier::class,
            $emailVerifier
        );

        try {
            $client->request(
                'GET',
                '/verify/email?id=' . $userId
            );

            self::assertResponseIsSuccessful();

            self::assertSelectorTextContains(
                'h1',
                'Confirmation réussie !'
            );

            self::assertSelectorTextContains(
                'p',
                'Votre adresse e-mail a été confirmée avec succès.'
            );
        } finally {
            $this->supprimerUtilisateur($userId);
        }
    }

    public function testEmailConfirmationStatusInitiallyFalse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/verify/email/status');

        self::assertResponseIsSuccessful();

        $data = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        self::assertSame(false, $data['confirmed']);

        self::assertSame(
            'Votre adresse e-mail est confirmée. Vous pouvez maintenant vous connecter.',
            $data['message']
        );
    }

    public function testEmailConfirmationStatusIsConsumedAfterReading(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $user = $this->creerUtilisateur();
        $userId = $user->getIdUser();

        $emailVerifier = $this->createMock(EmailVerifier::class);

        $emailVerifier->expects(self::once())
            ->method('handleEmailConfirmation');

        static::getContainer()->set(
            EmailVerifier::class,
            $emailVerifier
        );

        try {
            // Confirmer l'adresse.
            $client->request(
                'GET',
                '/verify/email?id=' . $userId
            );

            self::assertResponseIsSuccessful();

            // Première lecture : le statut doit être true.
            $client->request('GET', '/verify/email/status');

            $data = json_decode(
                $client->getResponse()->getContent(),
                true
            );

            self::assertSame(true, $data['confirmed']);

            // Deuxième lecture : le statut doit être false.
            $client->request('GET', '/verify/email/status');

            $data = json_decode(
                $client->getResponse()->getContent(),
                true
            );

            self::assertSame(false, $data['confirmed']);
        } finally {
            $this->supprimerUtilisateur($userId);
        }
    }
}
