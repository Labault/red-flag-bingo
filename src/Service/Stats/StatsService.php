<?php

namespace App\Service\Stats;

use App\Repository\BingoCardRepository;
use App\Repository\RedFlagRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Calcule les stats du dashboard admin avec un cache PSR-6 (TTL 5 min).
 * Les stats coûteuses sont mémoïsées pour éviter de retaper la BDD à chaque page.
 */
final class StatsService
{
    private const CACHE_PREFIX = 'admin_stats_';
    private const CACHE_TTL    = 300; // 5 minutes

    public function __construct(
        private readonly BingoCardRepository $cardRepository,
        private readonly RedFlagRepository $redFlagRepository,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * @template T
     *
     * @param callable(): T $compute
     *
     * @return T
     */
    private function cached(string $key, callable $compute): mixed
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . $key);
        if ($item->isHit()) {
            /** @var T $hit */
            $hit = $item->get();

            return $hit;
        }
        $value = $compute();
        $item->set($value);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $value;
    }

    /**
     * Renvoie toutes les stats agrégées pour le dashboard.
     *
     * @return array<string, mixed>
     */
    public function getAllStats(): array
    {
        return [
            'cardsByTheme'        => $this->getCardsByTheme(),
            'cardsPerDay'         => $this->getCardsPerDay(30),
            'heatmap'             => $this->getHeatmap(90),
            'keyMetrics'          => $this->getKeyMetrics(),
            'redFlagsTotal'       => $this->getRedFlagsTotal(),
            'themesWithRedFlags'  => $this->getThemesWithRedFlagsCount(),
            'topWinningRedFlags'  => $this->getTopWinningRedFlags(10),
        ];
    }

    /**
     * Répartition des cartes par thème, formatée pour Chart.js (donut).
     *
     * @return array{data: list<int>, emojis: list<string>, labels: list<string>}
     */
    public function getCardsByTheme(): array
    {
        return $this->cached('cards_by_theme', function () {
            $rows = $this->cardRepository->countByTheme();

            $labels = [];
            $data   = [];
            $emojis = [];
            foreach ($rows as $row) {
                $labels[] = $row['theme']->getName();
                $emojis[] = $row['theme']->getEmoji();
                $data[]   = $row['count'];
            }

            return ['data' => $data, 'emojis' => $emojis, 'labels' => $labels];
        });
    }

    /**
     * Cartes créées par jour sur N jours, formatées pour Chart.js.
     *
     * @return array{data: list<int>, labels: list<string>}
     */
    public function getCardsPerDay(int $days): array
    {
        return $this->cached("cards_per_day_$days", function () use ($days) {
            $byDay = $this->cardRepository->countByDay($days);

            // On remplit les jours manquants avec 0 pour avoir un graphique continu
            $labels = [];
            $data   = [];
            $cursor = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days - 1))->setTime(0, 0);
            $end    = (new \DateTimeImmutable())->setTime(0, 0);

            while ($cursor <= $end) {
                $key      = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $data[]   = $byDay[$key] ?? 0;
                $cursor   = $cursor->modify('+1 day');
            }

            return ['data' => $data, 'labels' => $labels];
        });
    }

    /**
     * Heatmap façon GitHub : par jour sur N jours, avec 5 niveaux d'intensité.
     *
     * @return array{cells: list<array{count: int, date: string, dayOfWeek: int, displayDate: string, level: int}>, max: int}
     */
    public function getHeatmap(int $days): array
    {
        return $this->cached("heatmap_$days", function () use ($days) {
            $byDay = $this->cardRepository->countByDay($days);

            $cells  = [];
            $cursor = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days - 1))->setTime(0, 0);
            $end    = (new \DateTimeImmutable())->setTime(0, 0);

            $max = empty($byDay) ? 0 : max($byDay);

            while ($cursor <= $end) {
                $key     = $cursor->format('Y-m-d');
                $count   = $byDay[$key] ?? 0;
                $cells[] = [
                    'count'       => $count,
                    'date'        => $cursor->format('Y-m-d'),
                    'dayOfWeek'   => (int) $cursor->format('N'), // 1=lundi, 7=dimanche
                    'displayDate' => $cursor->format('d/m'),
                    'level'       => $this->intensityLevel($count, $max),
                ];
                $cursor = $cursor->modify('+1 day');
            }

            return ['cells' => $cells, 'max' => $max];
        });
    }

    /**
     * 4 chiffres clés : cartes totales, bingos, taux de bingo, cartes 7 derniers jours.
     *
     * @return array{bingoRate: float|int, bingos: int, last7Days: int, total: int, trend: float|int|null}
     */
    public function getKeyMetrics(): array
    {
        return $this->cached('key_metrics', function () {
            $total = $this->cardRepository->countAll();
            $bingos = $this->cardRepository->countWithBingo();

            $sevenDaysAgo    = (new \DateTimeImmutable())->modify('-7 days');
            $fourteenDaysAgo = (new \DateTimeImmutable())->modify('-14 days');

            $last7     = $this->cardRepository->countSince($sevenDaysAgo);
            $previous7 = $this->cardRepository->countSince($fourteenDaysAgo) - $last7;

            $trend = null;
            if ($previous7 > 0) {
                $trend = round((($last7 - $previous7) / $previous7) * 100);
            } elseif ($last7 > 0) {
                $trend = 100; // Si rien la semaine d'avant et au moins 1 cette semaine = +100%
            }

            $bingoRate = $total > 0 ? round(($bingos / $total) * 100, 1) : 0;

            return [
                'bingoRate' => $bingoRate,
                'bingos'    => $bingos,
                'last7Days' => $last7,
                'total'     => $total,
                'trend'     => $trend,
            ];
        });
    }

    /**
     * Nombre total de red flags actifs (non archivés).
     */
    public function getRedFlagsTotal(): int
    {
        return $this->cached('red_flags_total', function () {
            return $this->redFlagRepository->countActive();
        });
    }

    /**
     * Nombre de thèmes ayant au moins un red flag actif.
     */
    public function getThemesWithRedFlagsCount(): int
    {
        return $this->cached('themes_with_red_flags', function () {
            return $this->redFlagRepository->countDistinctActiveThemes();
        });
    }

    /**
     * Top N red flags qui ont contribué à des bingos, en global et regroupés par thème.
     *
     * @return array<string, mixed>
     */
    public function getTopWinningRedFlags(int $limit): array
    {
        return $this->cached("top_winning_grouped_$limit", function () use ($limit) {
            return $this->cardRepository->topWinningRedFlags($limit);
        });
    }

    private function intensityLevel(int $count, int $max): int
    {
        if (0 === $count) return 0;
        if (0 === $max) return 0;
        $ratio = $count / $max;
        if ($ratio < 0.25) return 1;
        if ($ratio < 0.5) return 2;
        if ($ratio < 0.75) return 3;
        return 4;
    }

    /**
     * Vide le cache (utile lors d'imports ou suppressions massives).
     */
    public function invalidateCache(): void
    {
        $this->cache->clear();
    }
}
