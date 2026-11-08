<?php

namespace App\Tests\Controller;

use App\Entity\Film;
use App\Entity\Reservation;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaiementControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var array<int, array{0: class-string, 1: array}> [classe, identifiants], supprimés en sens inverse */
    private array $creees = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        try {
            ApiRequestor::setHttpClient(CurlClient::instance());

            $em = $this->em();
            $em->clear();

            foreach (array_reverse($this->creees) as [$classe, $ids]) {
                $entite = $em->find($classe, $ids);
                if ($entite !== null) {
                    $em->remove($entite);
                    $em->flush();
                }
            }
        } catch (\Throwable) {
            // le nettoyage ne doit jamais empêcher l'arrêt du kernel
        } finally {
            parent::tearDown();
        }
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persister(object $entite): object
    {
        $this->em()->persist($entite);
        $this->em()->flush();
        $this->creees[] = [
            $entite::class,
            $this->em()->getClassMetadata($entite::class)->getIdentifierValues($entite),
        ];

        return $entite;
    }

    /** Faux Stripe : répond sans réseau, avec le payment_status voulu. */
    private function fauxStripe(string $paymentStatus = 'paid'): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('request')->willReturn([
            json_encode([
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.test/pay/cs_test_123',
                'payment_status' => $paymentStatus,
            ]),
            200,
            [],
        ]);

        ApiRequestor::setHttpClient($client);
    }

    private function creerUtilisateur(): Utilisateur
    {
        $user = (new Utilisateur())
            ->setNom('Test')
            ->setPrenom('Paiement')
            ->setEmail(uniqid('paiement_', true) . '@example.com')
            ->setMdp('x')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);

        return $this->persister($user);
    }

    private function creerReservation(Utilisateur $user, ?string $statut = null): Reservation
    {
        $film = $this->persister(
            (new Film())->setNomFilm('Film de test')->setDescription('Description de test')
        );
        $seance = $this->persister(
            (new Seance())
                ->setFilm($film)
                ->setDateSeance(new \DateTime('+1 day'))
                ->setHoraireSeance(new \DateTime('20:00'))
        );

        $reservation = (new Reservation())->setUtilisateur($user)->setSeance($seance);
        if ($statut !== null) {
            $reservation->setStatut($statut);
        }

        return $this->persister($reservation);
    }

    // ---------- Accès ----------

    public function testAnonymeRedirigeVersLogin(): void
    {
        $this->client->request('GET', '/paiement/1');

        self::assertResponseRedirects('/login');
    }

    public function testReservationInexistante404(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/paiement/999999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testReservationDUnAutreUtilisateur403(): void
    {
        $reservation = $this->creerReservation($this->creerUtilisateur());
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/paiement/' . $reservation->getIdReservation());

        self::assertResponseStatusCodeSame(403);
    }

    // ---------- payer ----------

    public function testPayerRedirigeVersStripeEtEnregistreLaSession(): void
    {
        $this->fauxStripe();
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $reservation->getIdReservation());

        self::assertResponseRedirects('https://checkout.stripe.test/pay/cs_test_123');
        self::assertSame('cs_test_123', $reservation->getStripeSessionId());
    }

    public function testPayerReservationDejaPayeeRedirigeVersProfil(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user, Reservation::STATUT_PAYEE);
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $reservation->getIdReservation());

        self::assertResponseRedirects('/profil');
    }

    // ---------- succès ----------

    public function testSuccesSansSessionStripe404(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $reservation->getIdReservation() . '/succes');

        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('statutsStripe')]
    public function testSuccesSelonLeStatutStripe(string $paymentStatus, bool $payeeAttendue): void
    {
        $this->fauxStripe($paymentStatus);
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);
        $reservation->setStripeSessionId('cs_test_123');
        $this->em()->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $reservation->getIdReservation() . '/succes');

        self::assertResponseRedirects('/profil');
        self::assertSame($payeeAttendue, $reservation->isPayee());
    }

    public static function statutsStripe(): iterable
    {
        yield 'payé' => ['paid', true];
        yield 'non payé' => ['unpaid', false];
    }

    // ---------- annulation ----------

    public function testAnnulationSupprimeLaReservationNonPayee(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);
        $id = $reservation->getIdReservation();
        $seanceId = $reservation->getSeance()->getIdSeance();
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $id . '/annule');

        self::assertResponseRedirects(
            static::getContainer()->get('router')->generate('app_reservation', ['id' => $seanceId])
        );
        self::assertNull($this->em()->find(Reservation::class, $id));
    }

    public function testAnnulationConserveLaReservationPayee(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user, Reservation::STATUT_PAYEE);
        $id = $reservation->getIdReservation();
        $this->client->loginUser($user);

        $this->client->request('GET', '/paiement/' . $id . '/annule');

        self::assertResponseRedirects();
        self::assertNotNull($this->em()->find(Reservation::class, $id));
    }
}