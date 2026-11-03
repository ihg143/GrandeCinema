<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfilControllerTest extends WebTestCase
{
    public function testProfileRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil');

        $this->assertResponseRedirects('/login');
    }

    public function testConnectedUserCanAccessProfile(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('test_') . '@example.com');
        $user->setMdp('password_test');

        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/profil');

        $this->assertResponseIsSuccessful();
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
