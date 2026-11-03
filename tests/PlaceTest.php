<?php

namespace App\Tests\Entity;

use App\Entity\Place;
use PHPUnit\Framework\TestCase;

class PlaceTest extends TestCase
{
    public function testInitialValues(): void
    {
        $place = new Place();

        self::assertNull($place->getIdPlace());
        self::assertNull($place->getNumeroPlace());
        self::assertNull($place->getSalle());
    }

    public function testGettersAndSetters(): void
    {
        $place = new Place();

        $place->setNumeroPlace(15);
        $place->setSalle(2);

        self::assertSame(15, $place->getNumeroPlace());
        self::assertSame(2, $place->getSalle());
    }

    public function testNumeroPlaceCanBeChanged(): void
    {
        $place = new Place();

        $place->setNumeroPlace(10);
        $place->setNumeroPlace(20);

        self::assertSame(20, $place->getNumeroPlace());
    }

    public function testSalleCanBeChanged(): void
    {
        $place = new Place();

        $place->setSalle(1);
        $place->setSalle(3);

        self::assertSame(3, $place->getSalle());
    }

    public function testSettersReturnSameInstance(): void
    {
        $place = new Place();

        self::assertSame($place, $place->setNumeroPlace(5));
        self::assertSame($place, $place->setSalle(2));
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $place = new Place();

        self::assertNull($place->getIdPlace());
    }
}
