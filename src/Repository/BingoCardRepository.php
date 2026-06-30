<?php

namespace App\Repository;

use App\Entity\BingoCard;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BingoCard>
 */
class BingoCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BingoCard::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les cartes créées par jour sur les N derniers jours.
     *
     * @return array<string, int> Indexé par date 'Y-m-d'
     */
    public function countByDay(int $days): array
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->setTime(0, 0);

        /** @var list<array{day: string, cnt: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            "SELECT TO_CHAR(created_at::date, 'YYYY-MM-DD') AS day, COUNT(*) AS cnt
             FROM bingo_card
             WHERE created_at >= :since
             GROUP BY day
             ORDER BY day ASC",
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = (int) $row['cnt'];
        }

        return $byDay;
    }

    /**
     * Répartition des cartes par thème.
     *
     * @return array<int, array{theme: Theme, count: int}>
     */
    public function countByTheme(): array
    {
        /** @var list<array{theme_id: int|string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.theme) AS theme_id, COUNT(c.id) AS cnt')
            ->groupBy('c.theme')
            ->getQuery()
            ->getScalarResult();

        if (empty($rows)) {
            return [];
        }

        $themeIds = array_map(fn (array $r): int => (int) $r['theme_id'], $rows);
        $themes = $this->getEntityManager()
            ->getRepository(Theme::class)
            ->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $themeIds)
            ->getQuery()
            ->getResult();

        $themesById = [];
        foreach ($themes as $theme) {
            $themesById[(int) $theme->getId()] = $theme;
        }

        $result = [];
        foreach ($rows as $row) {
            $themeId = (int) $row['theme_id'];
            if (isset($themesById[$themeId])) {
                $result[] = [
                    'count' => (int) $row['cnt'],
                    'theme' => $themesById[$themeId],
                ];
            }
        }

        // Tri par count décroissant
        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    public function countSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithBingo(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.bingoReachedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top N red flags qui apparaissent le plus dans les cartes ayant atteint le bingo,
     * en version globale et regroupée par thème.
     * Note : on travaille sur le tableau cells (JSON) qui contient les IDs des red flags.
     *
     * @return array{
     *     global: array<int, array{redFlag: \App\Entity\RedFlag, count: int}>,
     *     byTheme: array<int, array{theme: Theme, flags: array<int, array{redFlag: \App\Entity\RedFlag, count: int}>}>
     * }
     */
    public function topWinningRedFlags(int $limit = 10): array
    {
        /** @var list<array{cells: string, markedCells: string}> $cards */
        $cards = $this->createQueryBuilder('c')
            ->select('c.cells, c.markedCells')
            ->where('c.bingoReachedAt IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        if (empty($cards)) {
            return ['byTheme' => [], 'global' => []];
        }

        // Comptage des occurrences dans les cellules MARQUÉES (= ayant contribué au bingo)
        // cells / markedCells sont des colonnes JSON : scalarResult les renvoie en chaîne.
        $counts = [];
        foreach ($cards as $card) {
            $cells  = json_decode($card['cells'], true);
            $marked = json_decode($card['markedCells'], true);

            if (!is_array($cells) || !is_array($marked)) {
                continue;
            }

            foreach ($marked as $position) {
                if (!is_int($position)) {
                    continue;
                }
                $redFlagId = $cells[$position] ?? null;
                if (!is_int($redFlagId)) {
                    continue;
                }
                $counts[$redFlagId] = ($counts[$redFlagId] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return ['byTheme' => [], 'global' => []];
        }

        // Récupération de TOUS les red flags concernés (incluant archivés pour conserver l'historique)
        $redFlagRepo = $this->getEntityManager()->getRepository(\App\Entity\RedFlag::class);
        $filters     = $this->getEntityManager()->getFilters();
        $wasEnabled  = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            $redFlags = $redFlagRepo->findBy(['id' => array_keys($counts)]);
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }

        $redFlagsById = [];
        foreach ($redFlags as $rf) {
            $redFlagsById[(int) $rf->getId()] = $rf;
        }

        // Tri global décroissant
        arsort($counts);

        $global  = [];
        $byTheme = [];
        foreach ($counts as $id => $count) {
            if (!isset($redFlagsById[$id])) continue;
            $redFlag = $redFlagsById[$id];
            $entry   = ['count' => $count, 'redFlag' => $redFlag];

            if (count($global) < $limit) {
                $global[] = $entry;
            }

            $theme = $redFlag->getTheme();
            $themeId = (int) $theme->getId();
            if (!isset($byTheme[$themeId])) {
                $byTheme[$themeId] = ['flags' => [], 'theme' => $theme, 'totalCount' => 0];
            }
            $byTheme[$themeId]['totalCount'] += $count;
            if (count($byTheme[$themeId]['flags']) < $limit) {
                $byTheme[$themeId]['flags'][] = $entry;
            }
        }

        // Tri des thèmes par contribution totale aux bingos, décroissante
        uasort($byTheme, fn (array $a, array $b): int => $b['totalCount'] <=> $a['totalCount']);

        return ['byTheme' => array_values($byTheme), 'global' => $global];
    }
}
