<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'Seances')]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idSeance', type: 'integer')]
    private ?int $idSeance = null;

    #[ORM\Column(name: 'dateSeance', type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateSeance = null;

    #[ORM\Column(name: 'horaireSeance', type: Types::TIME_MUTABLE)]
    private ?\DateTime $horaireSeance = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'idFilm', referencedColumnName: 'idFilm', nullable: false)]
    private ?Film $film = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(mappedBy: 'seance', targetEntity: Reservation::class)]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getIdSeance(): ?int
    {
        return $this->idSeance;
    }

    public function getDateSeance(): ?\DateTime
    {
        return $this->dateSeance;
    }

    public function setDateSeance(\DateTime $dateSeance): static
    {
        $this->dateSeance = $dateSeance;
        return $this;
    }

    public function getHoraireSeance(): ?\DateTime
    {
        return $this->horaireSeance;
    }

    public function setHoraireSeance(\DateTime $horaireSeance): static
    {
        $this->horaireSeance = $horaireSeance;
        return $this;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(?Film $film): static
    {
        $this->film = $film;
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setSeance($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }
}
