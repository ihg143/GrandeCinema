<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FilmControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/film');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'GrandCiné');
    }

    public function testDetail(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $film = new Film();
        $film->setNomFilm('Film de test');
        $film->setDescription('Description du film de test');
        $film->setImageFilm('film-test.jpg');
        $film->setGenre('Action');
        $film->setDuree(120);
        $film->setDateSortie(new \DateTime('2026-01-01'));

        $entityManager->persist($film);
        $entityManager->flush();

        $filmId = $film->getIdFilm();

        $client->request('GET', '/film/' . $filmId);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main.detail');

        $entityManager->remove($film);
        $entityManager->flush();
    }
}