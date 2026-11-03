<?php

namespace App\Tests\Entity;

use App\Entity\Place;
use PHPUnit\Framework\TestCase;

class PlaceTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $place = new Place();

        $place->setNumeroPlace(15);
        $place->setSalle(2);

        $this->assertSame(15, $place->getNumeroPlace());
        $this->assertSame(2, $place->getSalle());
    }

    public function testNumeroPlaceCanBeChanged(): void
    {
        $place = new Place();

        $place->setNumeroPlace(10);
        $place->setNumeroPlace(20);

        $this->assertSame(20, $place->getNumeroPlace());
    }

    public function testSalleCanBeChanged(): void
    {
        $place = new Place();

        $place->setSalle(1);
        $place->setSalle(3);

        $this->assertSame(3, $place->getSalle());
    }

    public function testSettersReturnSameInstance(): void
    {
        $place = new Place();

        $this->assertSame($place, $place->setNumeroPlace(5));
        $this->assertSame($place, $place->setSalle(2));
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $place = new Place();

        $this->assertNull($place->getIdPlace());
    }
}
