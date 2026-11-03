<?php

namespace App\Tests\Form;

use App\Entity\Film;
use App\Form\FilmType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
class FilmTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(
                Validation::createValidator()
            ),
        ];
    }

    public function testFormContainsAllFields(): void
    {
        $form = $this->factory->create(FilmType::class);

        self::assertTrue($form->has('nomFilm'));
        self::assertTrue($form->has('dateSortie'));
        self::assertTrue($form->has('genre'));
        self::assertTrue($form->has('imageFilm'));
        self::assertTrue($form->has('duree'));
        self::assertTrue($form->has('description'));
    }

    public function testFormUsesFilmEntity(): void
    {
        $film = new Film();

        $form = $this->factory->create(FilmType::class, $film);

        self::assertInstanceOf(Film::class, $form->getData());
        self::assertSame($film, $form->getData());
    }

    public function testFormHasCorrectLabels(): void
    {
        $form = $this->factory->create(FilmType::class);

        self::assertSame(
            'Titre du film',
            $form->get('nomFilm')->getConfig()->getOption('label')
        );

        self::assertSame(
            'Genre',
            $form->get('genre')->getConfig()->getOption('label')
        );

        self::assertSame(
            'Durée en minutes',
            $form->get('duree')->getConfig()->getOption('label')
        );
    }

    public function testFormSubmitsValidData(): void
    {
        $form = $this->factory->create(FilmType::class);

        $form->submit([
            'nomFilm' => 'Film de test',
            'dateSortie' => '2026-01-01',
            'genre' => 'Action',
            'imageFilm' => 'film-test.jpg',
            'duree' => 120,
            'description' => 'Description du film de test',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertInstanceOf(Film::class, $form->getData());
        self::assertSame('Film de test', $form->getData()->getNomFilm());
        self::assertSame('Action', $form->getData()->getGenre());
        self::assertSame(120, $form->getData()->getDuree());
    }
}
