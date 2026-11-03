<?php

namespace App\Tests\Controller;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VerifyEmailControllerTest extends WebTestCase
{
    public function testVerificationWithoutIdRedirectsToRegister(): void
    {
        $client = static::createClient();

        $client->request('GET', '/verify/email');

        $this->assertResponseRedirects('/register');
    }

    public function testVerificationWithUnknownUserRedirectsToRegister(): void
    {
        $client = static::createClient();

        $repository = $this->createStub(UtilisateurRepository::class);

        $repository->method('find')->willReturn(null);

        static::getContainer()->set(
            UtilisateurRepository::class,
            $repository
        );

        $client->request('GET', '/verify/email?id=999999');

        $this->assertResponseRedirects('/register');
    }

    public function testEmailConfirmationStatusInitiallyFalse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/verify/email/status');

        $this->assertResponseIsSuccessful();

        $data = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertSame(false, $data['confirmed']);

        $this->assertSame(
            'Votre adresse e-mail est confirmée. Vous pouvez maintenant vous connecter.',
            $data['message']
        );
    }
}
