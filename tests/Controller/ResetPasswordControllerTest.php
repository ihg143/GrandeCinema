<?php

namespace App\Tests\Controller;

use App\Entity\ResetPasswordRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    private const ANCIEN_MDP = 'AncienMdp-123!';
    private const NOUVEAU_MDP = 'Gc#Test-9xQ2vLp7zR!';

    private KernelBrowser $client;

    /** @var int[] */
    private array $userIds = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        $em = $this->em();
        $em->clear();

        foreach ($this->userIds as $id) {
            try {
                // Les demandes de réinitialisation référencent l'utilisateur : on les supprime d'abord
                $em->createQuery('DELETE FROM ' . ResetPasswordRequest::class . ' r WHERE r.user = :u')
                    ->setParameter('u', $id)
                    ->execute();
            } catch (\Throwable) {
            }

            try {
                if ($user = $em->find(Utilisateur::class, $id)) {
                    $em->remove($user);
                    $em->flush();
                }
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    // ---------- Helpers ----------

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function creerUtilisateur(): Utilisateur
    {
        $user = (new Utilisateur())
            ->setNom('Test')
            ->setPrenom('Reset')
            ->setEmail(uniqid('reset_', true) . '@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);
        $user->setMdp($this->hasher()->hashPassword($user, self::ANCIEN_MDP));

        $this->em()->persist($user);
        $this->em()->flush();
        $this->userIds[] = $user->getIdUser();

        return $user;
    }

    /** Génère un vrai jeton valide, comme celui envoyé par e-mail. */
    private function genererJeton(Utilisateur $user): string
    {
        $helper = static::getContainer()->get(ResetPasswordHelperInterface::class);

        return $helper->generateResetToken($user)->getToken();
    }

    private function formulaireContenant(Crawler $crawler, string $suffixeChamp): Form
    {
        $champ = $crawler->filter(sprintf('input[name$="%s"]', $suffixeChamp));
        self::assertGreaterThan(0, $champ->count(), "Champ $suffixeChamp introuvable sur la page.");

        return $champ->ancestors()->filter('form')->first()->form();
    }

    /** Remplit les champs dont le nom se termine par la clé donnée. */
    private function remplir(Form $form, array $valeurs): void
    {
        foreach (array_keys($form->getValues()) as $nom) {
            foreach ($valeurs as $suffixe => $valeur) {
                if (str_ends_with($nom, $suffixe)) {
                    $form[$nom] = $valeur;
                }
            }
        }
    }

    private function motDePasseValide(int $id, string $mdp): bool
    {
        $this->em()->clear();

        return $this->hasher()->isPasswordValid($this->em()->find(Utilisateur::class, $id), $mdp);
    }

    // ---------- Demande de réinitialisation ----------

    public function testPageDeDemandeAffichee(): void
    {
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
    }

    public function testDemandeAvecEmailInconnuRedirigeVersCheckEmail(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $this->formulaireContenant($crawler, '[email]');
        $this->remplir($form, ['[email]' => 'inconnu_' . uniqid() . '@example.com']);

        $this->client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
    }

    public function testDemandeAvecEmailConnuRedirigeVersCheckEmail(): void
    {
        $user = $this->creerUtilisateur();
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $this->formulaireContenant($crawler, '[email]');
        $this->remplir($form, ['[email]' => $user->getEmail()]);

        $this->client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
    }

    public function testPageCheckEmailAccessibleDirectement(): void
    {
        $this->client->request('GET', '/reset-password/check-email');

        self::assertResponseIsSuccessful();
    }

    // ---------- Lien reçu par e-mail ----------

    public function testSansJetonRenvoie404(): void
    {
        $this->client->request('GET', '/reset-password/reset');

        self::assertResponseStatusCodeSame(404);
    }

    public function testJetonInvalideRedirigeVersLaDemande(): void
    {
        $this->client->request('GET', '/reset-password/reset/jeton-invalide');
        self::assertResponseRedirects('/reset-password/reset');

        $this->client->followRedirect();
        self::assertResponseRedirects('/reset-password');
    }

    // ---------- Nouveau mot de passe ----------

    public function testReinitialisationComplete(): void
    {
        $user = $this->creerUtilisateur();
        $id = $user->getIdUser();
        $jeton = $this->genererJeton($user);

        // Le jeton est retiré de l'URL et stocké en session
        $this->client->request('GET', '/reset-password/reset/' . $jeton);
        self::assertResponseRedirects('/reset-password/reset');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $form = $this->formulaireContenant($crawler, '[plainPassword]' . ($crawler->filter('input[name$="[plainPassword][first]"]')->count() ? '[first]' : ''));
        $this->remplir($form, [
            '[plainPassword][first]' => self::NOUVEAU_MDP,
            '[plainPassword][second]' => self::NOUVEAU_MDP,
            '[plainPassword]' => self::NOUVEAU_MDP,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/');
        self::assertTrue($this->motDePasseValide($id, self::NOUVEAU_MDP));
        self::assertFalse($this->motDePasseValide($id, self::ANCIEN_MDP));
    }

    public function testMotDePasseTropCourtEstRefuse(): void
    {
        $user = $this->creerUtilisateur();
        $id = $user->getIdUser();
        $jeton = $this->genererJeton($user);

        $this->client->request('GET', '/reset-password/reset/' . $jeton);
        $crawler = $this->client->followRedirect();

        $form = $this->formulaireContenant($crawler, '[plainPassword]' . ($crawler->filter('input[name$="[plainPassword][first]"]')->count() ? '[first]' : ''));
        $this->remplir($form, [
            '[plainPassword][first]' => 'abc',
            '[plainPassword][second]' => 'abc',
            '[plainPassword]' => 'abc',
        ]);
        $this->client->submit($form);

        self::assertFalse($this->client->getResponse()->isRedirect(), 'Un mot de passe trop court ne doit pas être accepté.');
        self::assertTrue($this->motDePasseValide($id, self::ANCIEN_MDP));
    }
}
