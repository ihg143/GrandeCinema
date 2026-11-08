<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Place;
use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BilletControllerTest extends WebTestCase
{
    private function creerUtilisateur(string $email): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail($email);
        $user->setMdp('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function connecterUtilisateur(
        KernelBrowser $client,
        Utilisateur $user
    ): void {
        $client->loginUser($user);
    }

    private function creerReservation(
        Utilisateur $user,
        int $nombrePlaces = 0
    ): Reservation {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Création du film
        $film = new Film();
        $film->setNomFilm('Film test ' . uniqid());
        $film->setDescription('Description du film');
        $film->setImageFilm('test.jpg');
        $film->setGenre('Action');
        $film->setDuree(120);
        $film->setDateSortie(new \DateTime('2025-01-01'));

        $em->persist($film);

        // Création de la séance
        $seance = new Seance();
        $seance->setFilm($film);
        $seance->setDateSeance(new \DateTime('2030-01-15'));
        $seance->setHoraireSeance(new \DateTime('18:00'));
        $seance->setSalle(1);

        $em->persist($seance);

        // Création de la réservation
        $reservation = new Reservation();
        $reservation->setUtilisateur($user);
        $reservation->setSeance($seance);
        $reservation->setStatut(Reservation::STATUT_PAYEE);

        $em->persist($reservation);

        // Création des places réservées
        for ($i = 1; $i <= $nombrePlaces; $i++) {
            $place = new Place();
            $place->setNumeroPlace($i);
            $place->setSalle(1);

            $em->persist($place);

            $reservationPlace = new ReservationPlace();
            $reservationPlace->setPlace($place);
            $reservationPlace->setSeance($seance);

            $reservation->addReservationPlace($reservationPlace);
        }

        $em->flush();

        return $reservation;
    }

    public function testBilletRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/billet/1');

        self::assertResponseStatusCodeSame(302);
    }

    public function testBilletReturnsNotFoundForUnknownReservation(): void
    {
        $client = static::createClient();

        $user = $this->creerUtilisateur(
            uniqid('billet_') . '@example.com'
        );

        $this->connecterUtilisateur($client, $user);

        $client->request('GET', '/billet/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUserCannotAccessAnotherUsersBillet(): void
    {
        $client = static::createClient();

        $proprietaire = $this->creerUtilisateur(
            uniqid('proprietaire_') . '@example.com'
        );

        $autreUtilisateur = $this->creerUtilisateur(
            uniqid('autre_') . '@example.com'
        );

        $reservation = $this->creerReservation($proprietaire);

        $this->connecterUtilisateur($client, $autreUtilisateur);

        $client->request(
            'GET',
            '/billet/' . $reservation->getIdReservation()
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCanDownloadOwnBilletWithoutPlaces(): void
    {
        $client = static::createClient();

        $user = $this->creerUtilisateur(
            uniqid('billet_sans_place_') . '@example.com'
        );

        $reservation = $this->creerReservation($user);

        $this->connecterUtilisateur($client, $user);

        $client->request(
            'GET',
            '/billet/' . $reservation->getIdReservation()
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertResponseHeaderSame(
            'content-disposition',
            'inline; filename="billet-' .
            $reservation->getIdReservation() . '.pdf"'
        );

        self::assertStringStartsWith(
            '%PDF',
            $client->getResponse()->getContent()
        );
    }

    public function testUserCanDownloadBilletWithPlaces(): void
    {
        $client = static::createClient();

        $user = $this->creerUtilisateur(
            uniqid('billet_avec_places_') . '@example.com'
        );

        $reservation = $this->creerReservation($user, 2);

        $this->connecterUtilisateur($client, $user);

        $client->request(
            'GET',
            '/billet/' . $reservation->getIdReservation()
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');

        self::assertStringStartsWith(
            '%PDF',
            $client->getResponse()->getContent()
        );
    }
}
