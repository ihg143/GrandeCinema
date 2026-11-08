<?php

namespace App\Entity;

use App\Repository\ReservationPlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationPlaceRepository::class)]
#[ORM\Table(name: 'reservationplaces')]
#[ORM\UniqueConstraint(
    name: 'uniq_place_seance',
    columns: ['idPlace', 'idSeance']
)]
class ReservationPlace
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'reservationPlaces')]
    #[ORM\JoinColumn(
        name: 'idReservation',
        referencedColumnName: 'idReservation',
        nullable: false
    )]
    private ?Reservation $reservation = null;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(
        name: 'idPlace',
        referencedColumnName: 'idPlace',
        nullable: false
    )]
    private ?Place $place = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(
        name: 'idSeance',
        referencedColumnName: 'idSeance',
        nullable: false
    )]
    private ?Seance $seance = null;

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function getSeance(): ?Seance
    {
        return $this->seance;
    }

    public function setSeance(?Seance $seance): static
    {
        $this->seance = $seance;

        return $this;
    }
}
