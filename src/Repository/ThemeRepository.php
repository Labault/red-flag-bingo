<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    /**
     * @return Theme[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère, pour une liste de thèmes, les compteurs :
     * - red flags actifs
     * - red flags archivés
     * - bingo cards
     *
     * @param Theme[] $themes
     * @return array<int, array{active: int, archived: int, cards: int}>
     *         Indexé par theme ID.
     */
    public function getStatsForThemes(array $themes): array
    {
        if (empty($themes)) {
            return [];
        }

        $themeIds = array_map(fn (Theme $t): int => (int) $t->getId(), $themes);
        $em = $this->getEntityManager();

        // Red flags actifs (le filtre 'archived_red_flag' s'applique → archivés exclus)
        $active = $em->createQuery('
            SELECT IDENTITY(r.theme) AS theme_id, COUNT(r.id) AS cnt
            FROM App\Entity\RedFlag r
            WHERE IDENTITY(r.theme) IN (:ids)
            GROUP BY r.theme
        ')->setParameter('ids', $themeIds)->getResult();

        // Red flags archivés : on désactive temporairement le filtre
        $filters = $em->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            $archived = $em->createQuery('
                SELECT IDENTITY(r.theme) AS theme_id, COUNT(r.id) AS cnt
                FROM App\Entity\RedFlag r
                WHERE IDENTITY(r.theme) IN (:ids) AND r.archivedAt IS NOT NULL
                GROUP BY r.theme
            ')->setParameter('ids', $themeIds)->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }

        // Bingo cards
        $cards = $em->createQuery('
            SELECT IDENTITY(c.theme) AS theme_id, COUNT(c.id) AS cnt
            FROM App\Entity\BingoCard c
            WHERE IDENTITY(c.theme) IN (:ids)
            GROUP BY c.theme
        ')->setParameter('ids', $themeIds)->getResult();

        // Indexation des compteurs par theme ID
        $activeByTheme = [];
        foreach ($active as $row) {
            $activeByTheme[(int) $row['theme_id']] = (int) $row['cnt'];
        }
        $archivedByTheme = [];
        foreach ($archived as $row) {
            $archivedByTheme[(int) $row['theme_id']] = (int) $row['cnt'];
        }
        $cardsByTheme = [];
        foreach ($cards as $row) {
            $cardsByTheme[(int) $row['theme_id']] = (int) $row['cnt'];
        }

        // Construction de l'index final (toutes les clés sont garanties présentes)
        $stats = [];
        foreach ($themeIds as $id) {
            $stats[$id] = [
                'active'   => $activeByTheme[$id] ?? 0,
                'archived' => $archivedByTheme[$id] ?? 0,
                'cards'    => $cardsByTheme[$id] ?? 0,
            ];
        }

        return $stats;
    }
}
