<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Place;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GestionCinemaControllerTest extends WebTestCase
{
    private function connecterAdmin(KernelBrowser $client): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = new Utilisateur();
        $admin->setNom('Admin');
        $admin->setPrenom('Test');
        $admin->setEmail(uniqid('admin_gestion_') . '@example.com');
        $admin->setMdp('password');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);

        $em->persist($admin);
        $em->flush();

        $client->loginUser($admin);
    }

    private function creerFilm(): Film
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $film = new Film();
        $film->setNomFilm('Film de test ' . uniqid());
        $film->setDescription('Description du film de test');
        $film->setImageFilm('test.jpg');

        $em->persist($film);
        $em->flush();

        return $film;
    }

    private function creerPlace(int $salle = 1): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $place = new Place();
        $place->setNumeroPlace(1);
        $place->setSalle($salle);

        $em->persist($place);
        $em->flush();
    }

    private function tokenAjout(KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            '/admin/gestion/seances'
        );

        self::assertResponseIsSuccessful();

        $champToken = $crawler->filter(
            'form.form-ajout-seance input[name="_token"]'
        );

        self::assertCount(
            1,
            $champToken,
            'Le jeton CSRF du formulaire d’ajout est introuvable.'
        );

        return $champToken->attr('value');
    }

    private function tokenModification(
        KernelBrowser $client,
        int $id
    ): string {
        $crawler = $client->request(
            'GET',
            '/admin/gestion/seances',
            ['edit' => $id]
        );

        self::assertResponseIsSuccessful();

        $champToken = $crawler->filter(
            'form.form-ajout-seance input[name="_token"]'
        );

        self::assertCount(
            1,
            $champToken,
            'Le jeton CSRF du formulaire de modification est introuvable.'
        );

        return $champToken->attr('value');
    }

    private function tokenSuppression(
        KernelBrowser $client,
        int $id,
        string $nomFilm
    ): string {
        $crawler = $client->request(
            'GET',
            '/admin/gestion/seances',
            ['q' => $nomFilm]
        );

        self::assertResponseIsSuccessful();

        $champToken = $crawler->filterXPath(
            '//form[contains(@action, "/' . $id . '/supprimer")]//input[@name="_token"]'
        );

        self::assertCount(
            1,
            $champToken,
            'Le formulaire de suppression de la séance est introuvable.'
        );

        return $champToken->attr('value');
    }

    // AJOUT

    public function testAjoutAvecJetonInvalide(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $client->request('POST', '/admin/gestion/seances', [
            '_token' => 'faux_token',
        ]);

        self::assertResponseRedirects(
            '/admin/gestion/seances#formulaire-seance'
        );
    }

    public function testAjoutAvecDonneesInvalides(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $token = $this->tokenAjout($client);

        $client->request('POST', '/admin/gestion/seances', [
            '_token' => $token,
            'film' => 999999,
            'salle' => '1',
            'dateSeance' => 'date_invalide',
            'horaireSeance' => 'heure_invalide',
        ]);

        self::assertResponseRedirects(
            '/admin/gestion/seances#formulaire-seance'
        );
    }

    public function testAjoutDansUneSalleSansPlaces(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();
        $token = $this->tokenAjout($client);

        $client->request('POST', '/admin/gestion/seances', [
            '_token' => $token,
            'film' => $film->getIdFilm(),
            'salle' => '99',
            'dateSeance' => '2030-01-15',
            'horaireSeance' => '18:00',
        ]);

        self::assertResponseRedirects(
            '/admin/gestion/seances#formulaire-seance'
        );
    }

    public function testAjoutSeanceAvecSucces(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();
        $this->creerPlace(1);

        $token = $this->tokenAjout($client);

        $client->request('POST', '/admin/gestion/seances', [
            '_token' => $token,
            'film' => $film->getIdFilm(),
            'salle' => '1',
            'dateSeance' => '2030-01-15',
            'horaireSeance' => '18:00',
        ]);

        self::assertResponseRedirects('/admin/gestion/seances');

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $seance = $em->getRepository(Seance::class)->findOneBy([
            'film' => $film,
            'salle' => 1,
        ]);

        self::assertNotNull(
            $seance,
            'La séance aurait dû être créée.'
        );
    }

    // MODIFICATION

    public function testAffichageFormulaireModification(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();

        $crawler = $client->request(
            'GET',
            '/admin/gestion/seances',
            ['edit' => $id]
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form.form-ajout-seance');

        self::assertSelectorTextContains(
            'form.form-ajout-seance button[type="submit"]',
            'Enregistrer les modifications'
        );

        self::assertSame(
            (string) $id,
            $crawler->filter(
                'form.form-ajout-seance input[name="idSeance"]'
            )->attr('value')
        );
    }

    public function testModificationSeanceAvecSucces(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();
        $this->creerPlace(1);

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();
        $token = $this->tokenModification($client, $id);

        $client->request('POST', '/admin/gestion/seances', [
            'idSeance' => $id,
            '_token' => $token,
            'film' => $film->getIdFilm(),
            'salle' => '1',
            'dateSeance' => '2030-02-20',
            'horaireSeance' => '20:00',
        ]);

        self::assertResponseRedirects('/admin/gestion/seances');

        // Récupération de la séance depuis la base de données
        $seanceModifiee = $em->getRepository(Seance::class)->find($id);

        self::assertNotNull(
            $seanceModifiee,
            'La séance modifiée doit toujours exister.'
        );

        self::assertSame(
            '2030-02-20',
            $seanceModifiee->getDateSeance()->format('Y-m-d')
        );

        self::assertSame(
            '20:00',
            $seanceModifiee->getHoraireSeance()->format('H:i')
        );
    }

    public function testModificationAvecJetonInvalide(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();

        $client->request('POST', '/admin/gestion/seances', [
            'idSeance' => $id,
            '_token' => 'faux_token',
        ]);

        self::assertResponseRedirects(
            '/admin/gestion/seances?edit=' . $id . '#formulaire-seance'
        );
    }

    public function testModificationAvecDonneesInvalides(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();
        $token = $this->tokenModification($client, $id);

        $client->request('POST', '/admin/gestion/seances', [
            'idSeance' => $id,
            '_token' => $token,
            'film' => 999999,
            'salle' => '1',
            'dateSeance' => 'date_invalide',
            'horaireSeance' => 'heure_invalide',
        ]);

        self::assertResponseRedirects(
            '/admin/gestion/seances?edit=' . $id . '#formulaire-seance'
        );
    }

    // SUPPRESSION

    public function testSuppressionAvecJetonInvalide(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();

        $client->request(
            'POST',
            '/admin/gestion/seances/' . $id . '/supprimer',
            ['_token' => 'faux_token']
        );

        self::assertResponseRedirects('/admin/gestion/seances');

        self::assertNotNull(
            $em->getRepository(Seance::class)->find($id),
            'La séance ne doit pas être supprimée avec un jeton invalide.'
        );
    }

    public function testSuppressionSeanceAvecSucces(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $film = $this->creerFilm();

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setSalle(1);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($seance);
        $em->flush();

        $id = $seance->getIdSeance();

        $token = $this->tokenSuppression(
            $client,
            $id,
            $film->getNomFilm()
        );

        $client->request(
            'POST',
            '/admin/gestion/seances/' . $id . '/supprimer',
            ['_token' => $token]
        );

        self::assertResponseRedirects('/admin/gestion/seances');

        self::assertNull(
            $em->getRepository(Seance::class)->find($id),
            'La séance aurait dû être supprimée.'
        );
    }
}
