<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoadsSuccessfully(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Connexion');
    }

    public function testLoginWithInvalidCredentials(): void
{
    $client = static::createClient();

    $crawler = $client->request('GET', '/login');

    $form = $crawler->selectButton('Se connecter')->form([
        '_username' => 'invalid@example.com',
        '_password' => 'invalidpassword'
    ]);

    $client->submit($form);

    // Vérifie la redirection vers la page de connexion
    $this->assertResponseRedirects('/login');

    // Suit la redirection
    $client->followRedirect();

    // Vérifie que la page de connexion s'affiche
    $this->assertResponseIsSuccessful();

    // Vérifie le message d'erreur
    $this->assertSelectorTextContains(
        '.erreur',
        'Adresse e-mail ou mot de passe incorrect'
    );
}
}
