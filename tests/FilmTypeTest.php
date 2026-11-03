<?php

namespace App\Tests\Form;

use App\Entity\Film;
use App\Form\FilmType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class FilmTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testFormContainsAllFields(): void
    {
        $form = $this->factory->create(FilmType::class);

        $this->assertTrue($form->has('nomFilm'));
        $this->assertTrue($form->has('dateSortie'));
        $this->assertTrue($form->has('genre'));
        $this->assertTrue($form->has('imageFilm'));
        $this->assertTrue($form->has('duree'));
        $this->assertTrue($form->has('description'));
    }

public function testFormUsesFilmEntity(): void
{
    $film = new Film();

    $form = $this->factory->create(FilmType::class, $film);

    $this->assertInstanceOf(Film::class, $form->getData());
}

    public function testFormHasCorrectLabels(): void
    {
        $form = $this->factory->create(FilmType::class);

        $this->assertSame('Titre du film', $form->get('nomFilm')->getConfig()->getOption('label'));
        $this->assertSame('Genre', $form->get('genre')->getConfig()->getOption('label'));
        $this->assertSame('Durée en minutes', $form->get('duree')->getConfig()->getOption('label'));
    }
}
