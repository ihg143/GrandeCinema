<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EmailChangeControllerTest extends WebTestCase
{
    private function createTestUser(): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('email_change_') . '@example.com');
        $user->setMdp('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testConfirmationWithoutIdRedirectsToProfil(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/confirmer-email');

        self::assertResponseRedirects('/profil');
    }

    public function testConfirmationWithInvalidIdRedirectsToProfil(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/confirmer-email?id=abc');

        self::assertResponseRedirects('/profil');
    }

    public function testConfirmationWithUnknownUserReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/confirmer-email?id=999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmationWithoutPendingEmailRedirectsToProfil(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser();

        $client->request(
            'GET',
            '/profil/confirmer-email?id=' . $user->getIdUser()
        );

        self::assertResponseRedirects('/profil');
    }

    public function testConfirmationWithPendingEmailAndInvalidLink(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser();

        $ancienneAdresse = $user->getEmail();
        $nouvelEmail = uniqid('nouveau_email_') . '@example.com';

        $user->setEmailEnAttente($nouvelEmail);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        // Le lien ne possède pas de signature valide.
        $client->request(
            'GET',
            '/profil/confirmer-email?id=' . $user->getIdUser()
        );

        self::assertResponseRedirects('/profil');

        $em->refresh($user);

        // L'adresse principale ne doit pas être modifiée.
        self::assertSame($ancienneAdresse, $user->getEmail());

        // La nouvelle adresse reste en attente.
        self::assertSame($nouvelEmail, $user->getEmailEnAttente());
    }
}
