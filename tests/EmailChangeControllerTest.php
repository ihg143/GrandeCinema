<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EmailChangeControllerTest extends WebTestCase
{
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

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('email_change_') . '@example.com');
        $user->setMdp('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);

        $em = static::getContainer()->get(
            \Doctrine\ORM\EntityManagerInterface::class
        );

        $em->persist($user);
        $em->flush();

        $client->request(
            'GET',
            '/profil/confirmer-email?id=' . $user->getIdUser()
        );

        self::assertResponseRedirects('/profil');
    }
}