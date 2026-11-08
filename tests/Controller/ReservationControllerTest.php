<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Place;
use App\Entity\Reservation;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReservationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    private Utilisateur $utilisateur;
    private Film $film;
    private Seance $seance;
    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $this->utilisateur = new Utilisateur();
        $this->utilisateur
            ->setNom('Test')
            ->setPrenom('Reservation')
            ->setEmail('reservation-' . bin2hex(random_bytes(6)) . '@example.com')
            ->setMdp('password-test')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);

        $this->film = new Film();
        $this->film
            ->setNomFilm('Film de test')
            ->setDescription('Description du film')
            ->setImageFilm('test.jpg');

        $this->seance = new Seance();
        $this->seance
            ->setFilm($this->film)
            ->setDateSeance(new \DateTime('+1 day'))
            ->setHoraireSeance(new \DateTime('18:00'))
            ->setSalle(1);

        $this->place = new Place();
        $this->place
            ->setNumeroPlace(1)
            ->setSalle(1);

        $this->entityManager->persist($this->utilisateur);
        $this->entityManager->persist($this->film);
        $this->entityManager->persist($this->seance);
        $this->entityManager->persist($this->place);
        $this->entityManager->flush();
    }

    private function getReservationCsrfToken(): string
    {
        $crawler = $this->client->request(
            'GET',
            '/reservation/' . $this->seance->getIdSeance()
        );

        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->assertNotEmpty($token);

        return $token;
    }

    public function testReservationPageRequiresAuthentication(): void
    {
        $this->client->request(
            'GET',
            '/reservation/' . $this->seance->getIdSeance()
        );

        $this->assertResponseRedirects();
    }

    public function testConnectedUserCanAccessReservationPage(): void
    {
        $this->client->loginUser($this->utilisateur);

        $this->client->request(
            'GET',
            '/reservation/' . $this->seance->getIdSeance()
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Réserver vos places');
    }

    public function testUnknownSeanceReturns404(): void
    {
        $this->client->loginUser($this->utilisateur);

        $this->client->request('GET', '/reservation/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $this->client->loginUser($this->utilisateur);

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => 'invalid-token',
                'places' => [$this->place->getIdPlace()],
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testEmptyPlaceSelectionRedirects(): void
    {
        $this->client->loginUser($this->utilisateur);

        $token = $this->getReservationCsrfToken();

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => $token,
                'places' => [],
            ]
        );

        $this->assertResponseRedirects();
    }

    public function testInvalidPlaceIdRedirects(): void
    {
        $this->client->loginUser($this->utilisateur);

        $token = $this->getReservationCsrfToken();

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => $token,
                'places' => ['place-invalide'],
            ]
        );

        $this->assertResponseRedirects();
    }

    public function testNonexistentPlaceRedirects(): void
    {
        $this->client->loginUser($this->utilisateur);

        $token = $this->getReservationCsrfToken();

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => $token,
                'places' => [999999999],
            ]
        );

        $this->assertResponseRedirects();
    }

    public function testPlaceFromAnotherRoomIsRejected(): void
    {
        $anotherPlace = new Place();
        $anotherPlace
            ->setNumeroPlace(2)
            ->setSalle(2);

        $this->entityManager->persist($anotherPlace);
        $this->entityManager->flush();

        $this->client->loginUser($this->utilisateur);

        $token = $this->getReservationCsrfToken();

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => $token,
                'places' => [$anotherPlace->getIdPlace()],
            ]
        );

        $this->assertResponseRedirects();

$placeToRemove = $this->entityManager
    ->getRepository(Place::class)
    ->find($anotherPlace->getIdPlace());

if ($placeToRemove !== null) {
    $this->entityManager->remove($placeToRemove);
    $this->entityManager->flush();
}
    }

    public function testReservationCanBeCreatedSuccessfully(): void
    {
        $this->client->loginUser($this->utilisateur);

        $token = $this->getReservationCsrfToken();

        $this->client->request(
            'POST',
            '/reservation/' . $this->seance->getIdSeance(),
            [
                '_token' => $token,
                'places' => [$this->place->getIdPlace()],
            ]
        );

        $this->assertResponseRedirects();

        $reservation = $this->entityManager
            ->getRepository(Reservation::class)
            ->findOneBy(['utilisateur' => $this->utilisateur]);

        $this->assertNotNull($reservation);
    }
}
