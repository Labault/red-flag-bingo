<?php

namespace App\Service;

class BingoChecker
{
    public const GRID_SIZE = 5;

    /**
     * Toutes les lignes possibles de bingo dans une grille 5x5
     * (5 lignes + 5 colonnes + 2 diagonales = 12 lignes possibles).
     *
     * @return list<list<int>>
     */
    public static function getAllLines(): array
    {
        $lines = [];
        $size = self::GRID_SIZE;

        // Lignes horizontales
        for ($row = 0; $row < $size; $row++) {
            $line = [];
            for ($col = 0; $col < $size; $col++) {
                $line[] = $row * $size + $col;
            }
            $lines[] = $line;
        }

        // Lignes verticales
        for ($col = 0; $col < $size; $col++) {
            $line = [];
            for ($row = 0; $row < $size; $row++) {
                $line[] = $row * $size + $col;
            }
            $lines[] = $line;
        }

        // Diagonale principale (top-left -> bottom-right)
        $line = [];
        for ($i = 0; $i < $size; $i++) {
            $line[] = $i * $size + $i;
        }
        $lines[] = $line;

        // Diagonale secondaire (top-right -> bottom-left)
        $line = [];
        for ($i = 0; $i < $size; $i++) {
            $line[] = $i * $size + ($size - 1 - $i);
        }
        $lines[] = $line;

        return $lines;
    }

    /**
     * Retourne toutes les lignes gagnantes pour une liste de cases cochées.
     * Tableau vide = pas de bingo.
     *
     * @param int[] $markedCells
     * @return list<list<int>>
     */
    public function getWinningLines(array $markedCells): array
    {
        $marked = array_flip($markedCells); // pour des lookups O(1)
        $winning = [];

        foreach (self::getAllLines() as $line) {
            $allMarked = true;
            foreach ($line as $position) {
                if (!isset($marked[$position])) {
                    $allMarked = false;
                    break;
                }
            }

            if ($allMarked) {
                $winning[] = $line;
            }
        }

        return $winning;
    }

    /**
     * Retourne toutes les positions appartenant à au moins une ligne gagnante.
     *
     * @param int[] $markedCells
     * @return list<int>
     */
    public function getWinningPositions(array $markedCells): array
    {
        $positions = [];
        foreach ($this->getWinningLines($markedCells) as $line) {
            foreach ($line as $position) {
                $positions[$position] = true;
            }
        }

        return array_keys($positions);
    }

    public function hasBingo(array $markedCells): bool
    {
        return count($this->getWinningLines($markedCells)) > 0;
    }
}
