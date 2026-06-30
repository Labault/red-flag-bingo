<?php

namespace App\Tests\Service;

use App\Service\BingoChecker;
use PHPUnit\Framework\TestCase;

/**
 * La grille est indexée de 0 à 24, ligne par ligne :
 *
 *      0  1  2  3  4
 *      5  6  7  8  9
 *     10 11 12 13 14
 *     15 16 17 18 19
 *     20 21 22 23 24
 */
final class BingoCheckerTest extends TestCase
{
    private BingoChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new BingoChecker();
    }

    public function testAnEmptyGridHasNoBingo(): void
    {
        self::assertFalse($this->checker->hasBingo([]));
        self::assertSame([], $this->checker->getWinningLines([]));
    }

    public function testFourCellsInALineAreNotEnoughToWin(): void
    {
        // Toute la première ligne sauf la dernière case.
        $markedCells = [0, 1, 2, 3];

        self::assertFalse($this->checker->hasBingo($markedCells));
    }

    public function testACompletedRowWinsAndIsTheOnlyWinningLine(): void
    {
        $thirdRow = [10, 11, 12, 13, 14];

        self::assertTrue($this->checker->hasBingo($thirdRow));
        self::assertSame([$thirdRow], $this->checker->getWinningLines($thirdRow));
    }

    /**
     * Couvre les trois familles de lignes : colonne, diagonale principale,
     * diagonale secondaire. Un seul cas par famille suffit, la logique est
     * la même pour les variantes.
     *
     * @param int[] $markedCells
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('winningLineProvider')]
    public function testEveryKindOfLineCanWin(string $label, array $markedCells): void
    {
        self::assertTrue(
            $this->checker->hasBingo($markedCells),
            sprintf('La ligne "%s" aurait dû être gagnante.', $label),
        );
    }

    /**
     * @return iterable<string, array{string, int[]}>
     */
    public static function winningLineProvider(): iterable
    {
        yield 'colonne du milieu'    => ['colonne du milieu', [2, 7, 12, 17, 22]];
        yield 'diagonale principale' => ['diagonale principale', [0, 6, 12, 18, 24]];
        yield 'diagonale secondaire' => ['diagonale secondaire', [4, 8, 12, 16, 20]];
    }

    public function testCrossingLinesAreAllReportedAndPositionsAreDeduplicated(): void
    {
        // Première ligne + première colonne : elles se croisent en case 0.
        $firstRow    = [0, 1, 2, 3, 4];
        $firstColumn = [0, 5, 10, 15, 20];
        $markedCells = array_merge($firstRow, $firstColumn);

        $winningLines = $this->checker->getWinningLines($markedCells);
        self::assertCount(2, $winningLines, 'Une ligne ET une colonne complètes = deux lignes gagnantes.');

        // 5 + 5 cases, mais la case 0 est partagée : 9 positions distinctes.
        $winningPositions = $this->checker->getWinningPositions($markedCells);
        sort($winningPositions);
        self::assertSame([0, 1, 2, 3, 4, 5, 10, 15, 20], $winningPositions);
    }
}
