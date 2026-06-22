<?php

namespace App\Dto\Import;

use App\Enum\Rarity;
use Symfony\Component\Validator\Constraints as Assert;

final class RedFlagImportDto
{
    #[Assert\NotNull(message: 'La rareté est requise.')]
    public ?Rarity $rarity = null;

    #[Assert\NotBlank(message: 'Le texte du red flag est requis.')]
    #[Assert\Length(
        max: 200,
        maxMessage: 'Le texte du red flag ne doit pas dépasser {{ limit }} caractères.'
    )]
    public ?string $text = null;
}
