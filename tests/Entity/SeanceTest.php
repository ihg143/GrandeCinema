<?php

namespace App\Tests\Entity;

use App\Entity\Film;
use App\Entity\Reservation;
use App\Entity\Seance;
use PHPUnit\Framework\TestCase;

class SeanceTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $seance = new Seance();

        $date = new \DateTime('2026-11-15');
        $horaire = new \DateTime('14:30:00');

        $seance->setDateSeance($date);
        $seance->setHoraireSeance($horaire);
        $seance->setSalle(2);

        $this->assertSame($date, $seance->getDateSeance());
        $this->assertSame($horaire, $seance->getHoraireSeance());
        $this->assertSame(2, $seance->getSalle());
    }

    public function testSetAndGetFilm(): void
    {
        $seance = new Seance();
        $film = new Film();

        $seance->setFilm($film);

        $this->assertSame($film, $seance->getFilm());
    }

    public function testReservationsCollectionIsInitiallyEmpty(): void
    {
        $seance = new Seance();

        $this->assertCount(0, $seance->getReservations());
    }

    public function testAddReservation(): void
    {
        $seance = new Seance();
        $reservation = new Reservation();

        $seance->addReservation($reservation);

        $this->assertCount(1, $seance->getReservations());
        $this->assertSame($seance, $reservation->getSeance());
    }

    public function testRemoveReservation(): void
    {
        $seance = new Seance();
        $reservation = new Reservation();

        $seance->addReservation($reservation);
        $seance->removeReservation($reservation);

        $this->assertCount(0, $seance->getReservations());
    }
}
