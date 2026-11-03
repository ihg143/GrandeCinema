<?php

namespace App\Tests\Controller;

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

        $client->request('GET', '/film/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main.detail');
    }
}
