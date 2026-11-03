<?php

namespace App\Tests\Controller;

use App\Repository\FilmRepository;
use Doctrine\DBAL\Exception as DatabaseException;
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
    $this->assertSelectorTextContains('.affiche .titre-section', "À l'affiche");
    $this->assertSelectorTextContains('.seances .titre-section', 'Séances du jour');
}


    public function testAccueilRouteIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertRouteSame('app_accueil');
    }

    public function testDatabaseErrorReturns503(): void
    {
        $client = static::createClient();

        $filmRepository = $this->createMock(FilmRepository::class);

        $databaseException = new class('Erreur de base de données')
            extends \RuntimeException
            implements DatabaseException {
        };

        $filmRepository
            ->expects($this->once())
            ->method('findAll')
            ->willThrowException($databaseException);

        static::getContainer()->set(
            FilmRepository::class,
            $filmRepository
        );

        $client->request('GET', '/');

        $this->assertResponseStatusCodeSame(503);
    }
}
