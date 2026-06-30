<?php

namespace App\Form\Admin;

use App\Entity\Theme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Theme>
 */
final class ThemeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr'        => ['autofocus' => 'autofocus', 'class' => 'pop-input'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est requis.'),
                    new Assert\Length(max: 255),
                ],
                'label'       => 'Nom du thème',
            ])
            ->add('slug', TextType::class, [
                'attr'        => ['class' => 'pop-input', 'pattern' => '[a-z0-9]+(?:-[a-z0-9]+)*'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le slug est requis.'),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                        message: 'Format invalide (kebab-case requis).'
                    ),
                ],
                'help'        => 'Lettres minuscules, chiffres et tirets. Utilisé dans l\'URL.',
                'label'       => 'Slug',
            ])
            ->add('emoji', TextType::class, [
                'attr'        => ['class' => 'pop-input', 'maxlength' => 10],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'emoji est requis.'),
                    new Assert\Length(max: 10),
                ],
                'label'       => 'Emoji',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Theme::class,
        ]);
    }
}
