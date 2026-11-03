<?php

namespace App\Tests\Controller;

use App\Repository\FilmRepository;
use App\Repository\SeanceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccueilControllerTest extends WebTestCase
{
    public function testAccueilPageLoadsSuccessfully(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testAccueilPageDisplaysFilmsAndSeances(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testAccueilRouteIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertRouteSame('app_accueil');
    }
}
