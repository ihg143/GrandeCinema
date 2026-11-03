<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfilControllerTest extends WebTestCase
{
    private const MDP = 'Password123!';

    private KernelBrowser $client;
    private ?int $userId = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        if ($this->userId !== null) {
            $em = $this->em();
            $em->clear();
            if ($user = $em->find(Utilisateur::class, $this->userId)) {
                $em->remove($user);
                $em->flush();
            }
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    /** Crée un utilisateur, le connecte et retourne son id. */
    private function connecter(): Utilisateur
    {
        $user = (new Utilisateur())
            ->setNom('Dupont')
            ->setPrenom('Marie')
            ->setEmail(uniqid('profil_', true) . '@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);
        $user->setMdp($this->hasher()->hashPassword($user, self::MDP));

        $this->em()->persist($user);
        $this->em()->flush();
        $this->userId = $user->getIdUser();

        $this->client->loginUser($user);

        return $user;
    }

    private function bdd(): Utilisateur
    {
        $this->em()->clear();

        return $this->em()->find(Utilisateur::class, $this->userId);
    }

    private function post(string $url, array $data): void
    {
        $this->client->request('POST', $url, $data);
    }

    // ---------- Accès ----------

    public function testAnonymeRedirigeVersLogin(): void
    {
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/login');

        $this->client->request('POST', '/profil/supprimer-compte');
        self::assertResponseRedirects('/login');
    }

    public function testProfilAfficheLesInformations(): void
    {
        $user = $this->connecter();

        $this->client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Dupont');
        self::assertSelectorTextContains('body', $user->getEmail());
    }

    // ---------- Profil ----------

    public function testModificationNomPrenom(): void
    {
        $user = $this->connecter();

        $this->post('/profil', [
            'update_profile' => 1, 'nom' => ' Martin ', 'prenom' => 'Paul', 'email' => $user->getEmail(),
        ]);

        self::assertResponseRedirects('/profil');
        self::assertSame('Martin', $this->bdd()->getNom());
        self::assertSame('Paul', $this->bdd()->getPrenom());
    }

    public function testProfilInvalideRefuse(): void
    {
        $user = $this->connecter();
        $email = $user->getEmail();

        foreach ([['', 'Paul', $email], ['Martin', '  ', $email], ['Martin', 'Paul', 'pas-un-email']] as [$nom, $prenom, $mail]) {
            $this->post('/profil', ['update_profile' => 1, 'nom' => $nom, 'prenom' => $prenom, 'email' => $mail]);

            self::assertResponseRedirects('/profil');
            self::assertSame('Dupont', $this->bdd()->getNom());
            self::assertSame($email, $this->bdd()->getEmail());
        }
    }

    public function testNouvelEmailResteEnAttente(): void
    {
        $user = $this->connecter();
        $ancien = $user->getEmail();
        $nouveau = uniqid('nouveau_', true) . '@example.com';

        $this->post('/profil', ['update_profile' => 1, 'nom' => 'Dupont', 'prenom' => 'Marie', 'email' => $nouveau]);

        self::assertResponseRedirects('/profil');
        self::assertSame($ancien, $this->bdd()->getEmail());
        self::assertSame($nouveau, $this->bdd()->getEmailEnAttente());
    }

    // ---------- Mot de passe ----------

    public function testChangementMotDePasse(): void
    {
        $this->connecter();

        $this->post('/profil', [
            'update_password' => 1, 'current_password' => self::MDP,
            'new_password' => 'Nouveau456!', 'confirm_password' => 'Nouveau456!',
        ]);

        self::assertResponseRedirects('/profil');
        self::assertTrue($this->hasher()->isPasswordValid($this->bdd(), 'Nouveau456!'));
    }

    public function testChangementMotDePasseInvalideRefuse(): void
    {
        $this->connecter();

        foreach ([['mauvais', 'Nouveau456!', 'Nouveau456!'], [self::MDP, '', ''], [self::MDP, 'Nouveau456!', 'Autre789!']] as [$actuel, $new, $confirm]) {
            $this->post('/profil', [
                'update_password' => 1, 'current_password' => $actuel,
                'new_password' => $new, 'confirm_password' => $confirm,
            ]);

            self::assertResponseRedirects('/profil');
            self::assertTrue($this->hasher()->isPasswordValid($this->bdd(), self::MDP));
        }
    }

    // ---------- Suppression du compte ----------

    public function testSuppressionRefuseeSansCsrfOuAvecMauvaisMotDePasse(): void
    {
        $this->connecter();
        $crawler = $this->client->request('GET', '/profil');
        $token = $crawler->filter('form[action="/profil/supprimer-compte"] input[name="_token"]')->attr('value');

        $this->post('/profil/supprimer-compte', ['_token' => 'invalide', 'current_password' => self::MDP]);
        self::assertResponseRedirects('/profil');
        self::assertSame('Dupont', $this->bdd()->getNom());

        $this->post('/profil/supprimer-compte', ['_token' => $token, 'current_password' => 'mauvais']);
        self::assertResponseRedirects('/profil');
        self::assertSame('Dupont', $this->bdd()->getNom());
    }

    public function testSuppressionAnonymiseEtDeconnecte(): void
    {
        $this->connecter();
        $crawler = $this->client->request('GET', '/profil');
        $token = $crawler->filter('form[action="/profil/supprimer-compte"] input[name="_token"]')->attr('value');

        $this->post('/profil/supprimer-compte', ['_token' => $token, 'current_password' => self::MDP]);

        self::assertResponseRedirects('/');
        $user = $this->bdd();
        self::assertSame('Compte supprimé', $user->getNom());
        self::assertSame('compte-supprime-' . $this->userId . '@example.invalid', $user->getEmail());
        self::assertFalse($this->hasher()->isPasswordValid($user, self::MDP));

        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/login');
    }
}