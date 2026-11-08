<?php

namespace App\Tests\Entity;

use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class ReservationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $reservation = new Reservation();

        $this->assertNull($reservation->getIdReservation());
        $this->assertNull($reservation->getUtilisateur());
        $this->assertNull($reservation->getSeance());
        $this->assertSame(
            Reservation::STATUT_EN_ATTENTE,
            $reservation->getStatut()
        );
        $this->assertFalse($reservation->isPayee());
        $this->assertNull($reservation->getStripeSessionId());
        $this->assertCount(0, $reservation->getReservationPlaces());
        $this->assertSame(0, $reservation->getTotal());
    }

    public function testUtilisateurGetterAndSetter(): void
    {
        $reservation = new Reservation();
        $utilisateur = new Utilisateur();

        $reservation->setUtilisateur($utilisateur);

        $this->assertSame($utilisateur, $reservation->getUtilisateur());
    }

    public function testSeanceGetterAndSetter(): void
    {
        $reservation = new Reservation();
        $seance = new Seance();

        $reservation->setSeance($seance);

        $this->assertSame($seance, $reservation->getSeance());
    }

    public function testSettersCanAcceptNull(): void
    {
        $reservation = new Reservation();

        $reservation->setUtilisateur(null);
        $reservation->setSeance(null);

        $this->assertNull($reservation->getUtilisateur());
        $this->assertNull($reservation->getSeance());
    }

    public function testAddReservationPlace(): void
    {
        $reservation = new Reservation();
        $reservationPlace = new ReservationPlace();

        $reservation->addReservationPlace($reservationPlace);

        $this->assertCount(1, $reservation->getReservationPlaces());
        $this->assertSame(
            $reservation,
            $reservationPlace->getReservation()
        );
    }

    public function testAddingSamePlaceTwiceDoesNotDuplicateIt(): void
    {
        $reservation = new Reservation();
        $reservationPlace = new ReservationPlace();

        $reservation->addReservationPlace($reservationPlace);
        $reservation->addReservationPlace($reservationPlace);

        $this->assertCount(1, $reservation->getReservationPlaces());
    }

    public function testPaymentStatus(): void
    {
        $reservation = new Reservation();

        $this->assertFalse($reservation->isPayee());

        $reservation->setStatut(Reservation::STATUT_PAYEE);

        $this->assertTrue($reservation->isPayee());
        $this->assertSame('payee', $reservation->getStatut());
    }

    public function testStripeSessionId(): void
    {
        $reservation = new Reservation();

        $reservation->setStripeSessionId('cs_test_123');

        $this->assertSame(
            'cs_test_123',
            $reservation->getStripeSessionId()
        );

        $reservation->setStripeSessionId(null);

        $this->assertNull($reservation->getStripeSessionId());
    }

    public function testTotalCalculation(): void
    {
        $reservation = new Reservation();

        $this->assertSame(0, $reservation->getTotal());

        $reservation->addReservationPlace(new ReservationPlace());
        $this->assertSame(10, $reservation->getTotal());

        $reservation->addReservationPlace(new ReservationPlace());
        $this->assertSame(20, $reservation->getTotal());
    }
}
