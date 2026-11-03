<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private function createTestUser(array $roles): Utilisateur
    {
        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('admin_test_') . '@example.com');
        $user->setMdp('password');
        $user->setRoles($roles);
        $user->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function testAdminPageRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testRegularUserCannotAccessAdminPage(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser(['ROLE_USER']);

        $client->loginUser($user);

        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboard(): void
    {
        $client = static::createClient();

        $admin = $this->createTestUser(['ROLE_ADMIN']);

        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }
}