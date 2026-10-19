<?php

namespace App\Entity;

use App\Repository\FilmRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmRepository::class)]
#[ORM\Table(name: 'Films')]
class Film
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFilm', type: 'integer')]
    private ?int $idFilm = null;

    #[ORM\Column(name: 'nomFilm', length: 150)]
    private ?string $nomFilm = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private ?string $description = null;

    #[ORM\Column(name: 'imageFilm', length: 255, nullable: true)]
    private ?string $imageFilm = null;

    public function getIdFilm(): ?int
    {
        return $this->idFilm;
    }

    public function getNomFilm(): ?string
    {
        return $this->nomFilm;
    }

    public function setNomFilm(string $nomFilm): static
    {
        $this->nomFilm = $nomFilm;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImageFilm(): ?string
    {
        return $this->imageFilm;
    }

    public function setImageFilm(?string $imageFilm): static
    {
        $this->imageFilm = $imageFilm;
        return $this;
    }
}
