<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'Reservations')]
class Reservation
{
    public const PRIX_PLACE = 10; // en euros
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_PAYEE = 'payee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idReservation', type: 'integer')]
    private ?int $idReservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(
        name: 'idUser',
        referencedColumnName: 'idUser',
        nullable: false
    )]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(
        name: 'idSeance',
        referencedColumnName: 'idSeance',
        nullable: false
    )]
    private ?Seance $seance = null;

    #[ORM\OneToMany(
        mappedBy: 'reservation',
        targetEntity: ReservationPlace::class,
        cascade: ['persist']
    )]
    private Collection $reservationPlaces;

    #[ORM\Column(length: 20, options: ['default' => 'en_attente'])]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    public function __construct()
    {
        $this->reservationPlaces = new ArrayCollection();
    }

    public function getIdReservation(): ?int
    {
        return $this->idReservation;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

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

    /**
     * @return Collection<int, ReservationPlace>
     */
    public function getReservationPlaces(): Collection
    {
        return $this->reservationPlaces;
    }

    public function addReservationPlace(
        ReservationPlace $reservationPlace
    ): static {
        if (!$this->reservationPlaces->contains($reservationPlace)) {
            $this->reservationPlaces->add($reservationPlace);
            $reservationPlace->setReservation($this);
        }

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPayee(): bool
    {
        return $this->statut === self::STATUT_PAYEE;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->reservationPlaces->count() * self::PRIX_PLACE;
    }
}