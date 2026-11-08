<?php

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
#[ORM\Table(name: 'places')]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idPlace', type: 'integer')]
    private ?int $idPlace = null;

    #[ORM\Column(name: 'numeroPlace', type: 'integer')]
    private ?int $numeroPlace = null;

    #[ORM\Column(name: 'salle', type: 'integer')]
    private ?int $salle = null;

    public function getIdPlace(): ?int
    {
        return $this->idPlace;
    }

    public function getNumeroPlace(): ?int
    {
        return $this->numeroPlace;
    }

    public function setNumeroPlace(int $numeroPlace): static
    {
        $this->numeroPlace = $numeroPlace;
        return $this;
    }

    public function getSalle(): ?int
    {
        return $this->salle;
    }

    public function setSalle(int $salle): static
    {
        $this->salle = $salle;
        return $this;
    }
}
