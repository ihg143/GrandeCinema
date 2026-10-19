<?php

namespace App\Form;

use App\Entity\Film;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class FilmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomFilm', TextType::class, [
                'label' => 'Titre du film',
                'constraints' => [
                    new NotBlank(message: 'Le titre du film est obligatoire.'),
                    new Length(
                        min: 2,
                        max: 150,
                        minMessage: 'Le titre doit contenir au moins 2 caractères.',
                        maxMessage: 'Le titre ne peut pas dépasser 150 caractères.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Exemple : Dune',
                ],
            ])
            ->add('dateSortie', DateType::class, [
                'label' => 'Date de sortie',
                'widget' => 'single_text',
                'input' => 'datetime',
                'constraints' => [
                    new NotBlank(message: 'La date de sortie est obligatoire.'),
                ],
            ])
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'constraints' => [
                    new NotBlank(message: 'Le genre est obligatoire.'),
                    new Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le genre doit contenir au moins 2 caractères.',
                        maxMessage: 'Le genre ne peut pas dépasser 50 caractères.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Exemple : Science-fiction',
                ],
            ])
            ->add('imageFilm', FileType::class, [
                'label' => 'Affiche du film',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Choisis une image JPG, PNG ou WEBP.',
                        maxSizeMessage: 'L’image ne doit pas dépasser 5 Mo.',
                    ),
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée en minutes',
                'constraints' => [
                    new NotBlank(message: 'La durée du film est obligatoire.'),
                    new Positive(message: 'La durée doit être un nombre positif.'),
                ],
                'attr' => [
                    'placeholder' => 'Exemple : 155',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire.'),
                    new Length(
                        min: 10,
                        max: 2000,
                        minMessage: 'La description doit contenir au moins 10 caractères.',
                        maxMessage: 'La description ne peut pas dépasser 2000 caractères.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Décris le film en quelques phrases...',
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Film::class,
        ]);
    }
}
