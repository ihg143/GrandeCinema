<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoadsSuccessfully(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'invalid@example.com',
            '_password' => 'invalidpassword',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertResponseIsSuccessful();

self::assertSelectorTextContains(
    '.erreur',
    'Adresse e-mail ou mot de passe incorrect'
);
    }
}
