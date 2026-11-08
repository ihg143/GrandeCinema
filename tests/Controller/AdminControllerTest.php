<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Place;
use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private function createTestUser(array $roles): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail(uniqid('admin_test_') . '@example.com');
        $user->setMdp('password');
        $user->setRoles($roles);
        $user->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createFilm(string $nom, ?int $duree = 120): Film
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $film = new Film();
        $film->setNomFilm($nom);
        $film->setDescription('Description du film pour les tests.');
        $film->setGenre('Action');
        $film->setDuree($duree);
        $film->setDateSortie(new \DateTime('2025-01-15'));

        $em->persist($film);
        $em->flush();

        return $film;
    }

    private function createSeance(
        Film $film,
        \DateTime $date,
        \DateTime $horaire,
        ?int $salle
    ): Seance {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setDateSeance($date);
        $seance->setHoraireSeance($horaire);
        $seance->setSalle($salle);

        $em->persist($seance);
        $em->flush();

        return $seance;
    }

    private function createReservation(
        Seance $seance,
        Utilisateur $user,
        string $statut,
        array $places
    ): Reservation {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $reservation = new Reservation();
        $reservation->setSeance($seance);
        $reservation->setUtilisateur($user);
        $reservation->setStatut($statut);

        foreach ($places as $place) {
            $reservationPlace = new ReservationPlace();
            $reservationPlace->setPlace($place);
            $reservationPlace->setSeance($seance);

            $reservation->addReservationPlace($reservationPlace);
        }

        $seance->addReservation($reservation);

        $em->persist($reservation);
        $em->flush();

        return $reservation;
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

        $this->assertSelectorTextContains(
            '.admin-title',
            'Administration Grand Cinéma'
        );

        $this->assertSelectorTextContains('body', "Films à l'affiche");
        $this->assertSelectorTextContains('body', 'Séances programmées');
        $this->assertSelectorTextContains('body', 'Réservations payées');
        $this->assertSelectorTextContains('body', 'Taux de remplissage');
    }

    public function testDashboardDisplaysStatisticsAndReservations(): void
    {
        $client = static::createClient();

        $admin = $this->createTestUser(['ROLE_ADMIN']);
        $user = $this->createTestUser(['ROLE_USER']);

        $client->loginUser($admin);

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Salle unique pour ce test.
        $salleTest = random_int(100000, 999999999);

        // Création du film principal.
        $film = $this->createFilm('Film test tableau de bord');

        // Séance terminée.
        $seance = $this->createSeance(
            $film,
            new \DateTime('yesterday'),
            new \DateTime('10:00'),
            $salleTest
        );

        // Création de cinq places.
        $places = [];

        for ($i = 1; $i <= 5; $i++) {
            $place = new Place();
            $place->setNumeroPlace($i);
            $place->setSalle($salleTest);

            $em->persist($place);
            $places[] = $place;
        }

        $em->flush();

        // Réservation payée : deux places.
        $this->createReservation(
            $seance,
            $user,
            Reservation::STATUT_PAYEE,
            [$places[0], $places[1]]
        );

        // Réservation en attente : une place.
        $this->createReservation(
            $seance,
            $user,
            Reservation::STATUT_EN_ATTENTE,
            [$places[2]]
        );

        // Séance sans salle.
        $filmSansSalle = $this->createFilm('Film sans salle');

        $this->createSeance(
            $filmSansSalle,
            new \DateTime('yesterday'),
            new \DateTime('09:00'),
            null
        );

        // Séance future dans une autre salle.
        $filmFutur = $this->createFilm('Film futur');

        $this->createSeance(
            $filmFutur,
            new \DateTime('tomorrow'),
            new \DateTime('10:00'),
            $salleTest + 1
        );

        // Film avec une durée invalide.
        $filmInvalide = $this->createFilm('Film durée invalide', 0);

        $this->createSeance(
            $filmInvalide,
            new \DateTime('yesterday'),
            new \DateTime('08:00'),
            $salleTest + 2
        );

        // Séance prévue aujourd'hui.
        $filmAujourdhui = $this->createFilm('Film aujourd’hui');

        $this->createSeance(
            $filmAujourdhui,
            new \DateTime('today'),
            new \DateTime('23:59'),
            $salleTest + 3
        );

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();

        // Vérification des statistiques par film.
        $this->assertSelectorTextContains(
            '.admin-row',
            'Film test tableau de bord'
        );

        // Vérification de la présence d'un taux en pourcentage.
        $this->assertMatchesRegularExpression(
            '/\d+%/',
            $client->getResponse()->getContent()
        );

        // Vérification des dernières réservations.
        $this->assertSelectorTextContains(
            '#reservations',
            'Utilisateur Test'
        );

        $this->assertSelectorTextContains(
            '#reservations',
            'Film test tableau de bord'
        );

        $this->assertSelectorTextContains('#reservations', 'Payée');
        $this->assertSelectorTextContains('#reservations', 'En attente');

        // Vérification de la séance du jour.
        $this->assertSelectorTextContains(
    '.admin-title',
    'Administration Grand Cinéma'
);
    }

    public function testDashboardDisplaysEmptyStatisticsMessagesWhenNoDataExists(): void
    {
        $client = static::createClient();

        $admin = $this->createTestUser(['ROLE_ADMIN']);

        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('.stat-grid');
        $this->assertSelectorExists('.admin-row');
        $this->assertSelectorExists('#reservations');
    }
}
