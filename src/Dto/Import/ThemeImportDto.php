<?php

namespace App\Dto\Import;

use Symfony\Component\Validator\Constraints as Assert;

final class ThemeImportDto
{
    #[Assert\NotBlank(message: 'L\'emoji du thème est requis.')]
    #[Assert\Length(max: 10)]
    public ?string $emoji = null;

    #[Assert\NotBlank(message: 'Le nom du thème est requis.')]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    /**
     * @var RedFlagImportDto[]
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'Au moins un red flag est requis.')]
    public array $redFlags = [];

    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        message: 'Le slug doit être en kebab-case (lettres minuscules, chiffres, tirets).'
    )]
    public ?string $slug = null;
}
