<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AboutControllerTest extends WebTestCase
{
    public function testAboutPageLoadsSuccessfully(): void
    {
        $client = static::createClient();

        $client->request('GET', '/a-propos');

        $this->assertResponseIsSuccessful();
    }

    public function testAboutPageUsesCorrectRoute(): void
    {
        $client = static::createClient();

        $client->request('GET', '/a-propos');

        $this->assertResponseStatusCodeSame(200);
        $this->assertRouteSame('app_about');
    }
}
