<?php

namespace App\Form\Admin;

use App\Entity\RedFlag;
use App\Enum\Rarity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<RedFlag>
 */
final class RedFlagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextareaType::class, [
                'attr'        => [
                    'autofocus' => 'autofocus',
                    'class'     => 'pop-input',
                    'maxlength' => 200,
                    'rows'      => 2,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le texte est requis.'),
                    new Assert\Length(max: 200),
                ],
                'help'        => '200 caractères max.',
                'label'       => 'Texte du red flag',
            ])
            ->add('rarity', EnumType::class, [
                'attr'         => ['class' => 'pop-input'],
                'choice_label' => fn (Rarity $r) => sprintf('%s %s', $r->emoji(), $r->label()),
                'class'        => Rarity::class,
                'label'        => 'Rareté',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RedFlag::class,
        ]);
    }
}
