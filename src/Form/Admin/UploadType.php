<?php

namespace App\Form\Admin;

use App\Dto\Import\UploadDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'attr'  => [
                    'accept' => '.yaml,.yml',
                    'class'  => 'sr-only', // caché, on style le drop zone autour
                ],
                'label' => false,
            ])
            ->add('preview', SubmitType::class, [
                'attr'  => ['class' => 'pop-button'],
                'label' => '👀 Prévisualiser',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr'       => [
                'data-controller' => 'dropzone',
                'novalidate'      => 'novalidate',
            ],
            'data_class' => UploadDto::class,
        ]);
    }
}
