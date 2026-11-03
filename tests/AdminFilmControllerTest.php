<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

class AdminFilmControllerTest extends WebTestCase
{
    private const PREFIXE = 'ZZ Test AF ';

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
        try {
            $em = $this->em();
            $em->clear();

            $em->createQuery('DELETE FROM ' . Film::class . ' f WHERE f.nomFilm LIKE :p')
                ->setParameter('p', self::PREFIXE . '%')
                ->execute();

            foreach ($this->userIds as $id) {
                if ($user = $em->find(Utilisateur::class, $id)) {
                    $em->remove($user);
                }
            }
            $em->flush();
        } catch (\Throwable) {
            // le nettoyage ne doit jamais empêcher l'arrêt du kernel
        } finally {
            parent::tearDown();
        }
    }

    // ---------- Helpers ----------

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function connecter(array $roles): void
    {
        $user = (new Utilisateur())
            ->setNom('Test')
            ->setPrenom('AdminFilm')
            ->setEmail(uniqid('adminfilm_', true) . '@example.com')
            ->setMdp('x')
            ->setRoles($roles)
            ->setIsVerified(true);

        $this->em()->persist($user);
        $this->em()->flush();
        $this->userIds[] = $user->getIdUser();

        $this->client->loginUser($user);
    }

    private function creerFilm(string $nom, ?string $genre = null, ?\DateTime $date = null): Film
    {
        $film = (new Film())
            ->setNomFilm(self::PREFIXE . $nom)
            ->setDescription('Description de test')
            ->setGenre($genre)
            ->setDateSortie($date);

        $this->em()->persist($film);
        $this->em()->flush();

        return $film;
    }

    /** Retrouve le formulaire de film via son champ nomFilm, quel que soit son nom. */
    private function formulaireFilm(Crawler $crawler): Form
    {
        $champ = $crawler->filter('input[name$="[nomFilm]"]');
        self::assertGreaterThan(0, $champ->count(), 'Champ nomFilm introuvable sur la page.');

        return $champ->ancestors()->filter('form')->first()->form();
    }

    /** Remplit uniquement les champs qui existent dans le formulaire. */
    private function remplir(Form $form, array $valeurs): void
    {
        foreach (array_keys($form->getValues()) as $nom) {
            foreach ($valeurs as $champ => $valeur) {
                if (str_ends_with($nom, '[' . $champ . ']')) {
                    $form[$nom] = $valeur;
                }
            }
        }
    }

    /** Valeurs qui respectent toutes les contraintes de FilmType. */
    private function valeursValides(string $nom): array
    {
        return [
            'nomFilm' => self::PREFIXE . $nom,
            'dateSortie' => '2020-05-05',
            'genre' => 'ZZGenre',
            'duree' => '120',
            'description' => 'Une description assez longue.',
        ];
    }

    private function trouver(string $nom): ?Film
    {
        $this->em()->clear();

        return $this->em()->getRepository(Film::class)->findOneBy(['nomFilm' => self::PREFIXE . $nom]);
    }

    private function contenu(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    // ---------- Accès ----------

    public function testAnonymeRedirigeVersLogin(): void
    {
        $this->client->request('GET', '/admin/film');

        self::assertResponseRedirects('/login');
    }

    public function testUtilisateurSimpleInterdit(): void
    {
        $film = $this->creerFilm('Interdit');
        $id = $film->getIdFilm();
        $this->connecter(['ROLE_USER']);

        $this->client->request('GET', '/admin/film');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/admin/film/$id/modifier");
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', "/admin/film/$id/supprimer");
        self::assertResponseStatusCodeSame(403);
    }

    // ---------- Liste et filtres ----------

    public function testListeAfficheLesFilms(): void
    {
        $this->creerFilm('Alpha');
        $this->connecter(['ROLE_ADMIN']);

        $this->client->request('GET', '/admin/film');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::PREFIXE . 'Alpha', $this->contenu());
    }

    public function testFiltres(): void
    {
        $this->creerFilm('Alpha', 'ZZGenre', new \DateTime('2020-01-02'));
        $this->creerFilm('Beta');
        $this->connecter(['ROLE_ADMIN']);

        // Recherche par nom
        $this->client->request('GET', '/admin/film', ['q' => 'Alpha']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::PREFIXE . 'Alpha', $this->contenu());
        self::assertStringNotContainsString(self::PREFIXE . 'Beta', $this->contenu());

        // Par genre
        $this->client->request('GET', '/admin/film', ['genre' => 'ZZGenre']);
        self::assertStringContainsString(self::PREFIXE . 'Alpha', $this->contenu());
        self::assertStringNotContainsString(self::PREFIXE . 'Beta', $this->contenu());

        // Par date de sortie valide
        $this->client->request('GET', '/admin/film', ['dateSortie' => '2020-01-02']);
        self::assertStringContainsString(self::PREFIXE . 'Alpha', $this->contenu());
        self::assertStringNotContainsString(self::PREFIXE . 'Beta', $this->contenu());

        // Dates invalides : le filtre est ignoré sans erreur
        foreach (['2020-13-45', 'abc'] as $dateInvalide) {
            $this->client->request('GET', '/admin/film', ['dateSortie' => $dateInvalide]);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString(self::PREFIXE . 'Beta', $this->contenu());
        }
    }

    // ---------- Ajout ----------

    public function testAjoutDUnFilm(): void
    {
        $this->connecter(['ROLE_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/film');

        $form = $this->formulaireFilm($crawler);
        $this->remplir($form, $this->valeursValides('Ajout'));
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/film');
        self::assertNotNull($this->trouver('Ajout'));
    }

    public function testAjoutInvalideNeRedirigePas(): void
    {
        $this->connecter(['ROLE_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/film');

        $form = $this->formulaireFilm($crawler);
        $this->remplir($form, ['nomFilm' => ''] + $this->valeursValides('Invalide'));
        $this->client->submit($form);

        self::assertFalse($this->client->getResponse()->isRedirect(), 'Un formulaire invalide ne doit pas rediriger.');
    }

    // ---------- Modification ----------

    public function testModificationDUnFilm(): void
    {
        $film = $this->creerFilm('Avant');
        $id = $film->getIdFilm();
        $this->connecter(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', "/admin/film/$id/modifier");
        self::assertResponseIsSuccessful();

        $form = $this->formulaireFilm($crawler);
        $this->remplir($form, $this->valeursValides('Apres'));
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/film');
        self::assertNotNull($this->trouver('Apres'));
        self::assertNull($this->trouver('Avant'));
    }

    // ---------- Suppression ----------

    public function testSuppressionAvecJetonValide(): void
    {
        $film = $this->creerFilm('ASupprimer');
        $id = $film->getIdFilm();
        $this->connecter(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/admin/film');
        $jeton = $crawler->filter(sprintf('form[action="/admin/film/%d/supprimer"] input[name="_token"]', $id));
        self::assertCount(1, $jeton, 'Le formulaire de suppression (avec _token) doit être sur la page.');

        $this->client->request('POST', "/admin/film/$id/supprimer", ['_token' => $jeton->attr('value')]);

        self::assertResponseRedirects('/admin/film');
        self::assertNull($this->trouver('ASupprimer'));
    }

    public function testSuppressionAvecJetonInvalideConserveLeFilm(): void
    {
        $film = $this->creerFilm('Conserve');
        $id = $film->getIdFilm();
        $this->connecter(['ROLE_ADMIN']);

        $this->client->request('POST', "/admin/film/$id/supprimer", ['_token' => 'invalide']);

        self::assertResponseRedirects('/admin/film');
        self::assertNotNull($this->trouver('Conserve'));
    }
}