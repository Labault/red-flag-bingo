<?php

namespace App\Service\Import;

/**
 * Rapport d'un import (dry-run ou réel) : ce qui sera/a été créé, skippé, ou rejeté.
 */
final class ImportReport
{
    /** @var array<int, array{text: string, rarity: string}> */
    public array $createdRedFlags = [];

    /**
     * Compteurs des red flags actifs déjà présents dans le thème (avant import).
     * Permet de calculer correctement la jouabilité après import.
     *
     * @var array{common: int, rare: int, legendary: int}
     */
    public array $existingActiveByRarity = [
        'common'    => 0,
        'legendary' => 0,
        'rare'      => 0,
    ];

    public bool $isDryRun = true;

    /** @var array<int, array{text: string, reason: string}> */
    public array $skippedRedFlags = [];

    public bool $themeAlreadyExists = false;
    public ?string $themeEmoji = null;
    public ?string $themeName = null;
    public ?string $themeSlug = null;
    public bool $themeWillBeCreated = false;

    /** @var array<int, array{path: string, message: string}> */
    public array $validationErrors = [];

    public function hasErrors(): bool
    {
        return count($this->validationErrors) > 0;
    }

    /**
     * Calcule la jouabilité du thème après import :
     * red flags actifs déjà existants + ceux qui vont être créés.
     */
    public function isPlayable(): array
    {
        $byRarity = $this->existingActiveByRarity;

        foreach ($this->createdRedFlags as $rf) {
            $byRarity[$rf['rarity']]++;
        }

        $required = ['common' => 15, 'legendary' => 3, 'rare' => 7];
        $missing = [];
        foreach ($required as $rarity => $needed) {
            if ($byRarity[$rarity] < $needed) {
                $missing[$rarity] = $needed - $byRarity[$rarity];
            }
        }

        return [
            'byRarity' => $byRarity,
            'missing'  => $missing,
            'playable' => 0 === count($missing),
        ];
    }

    public function totalCreated(): int
    {
        return count($this->createdRedFlags);
    }

    public function totalSkipped(): int
    {
        return count($this->skippedRedFlags);
    }
}
