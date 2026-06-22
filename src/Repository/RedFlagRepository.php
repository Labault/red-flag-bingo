<?php

namespace App\Repository;

use App\Entity\RedFlag;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RedFlag>
 */
class RedFlagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RedFlag::class);
    }

    /**
     * Compte tous les red flags actifs (le filtre 'archived_red_flag' s'applique).
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les red flags actifs d'un thème (le filtre 'archived_red_flag' s'applique).
     */
    public function countActiveByTheme(Theme $theme): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.theme = :theme')
            ->setParameter('theme', $theme)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de thèmes distincts ayant au moins un red flag actif
     * (le filtre 'archived_red_flag' s'applique).
     */
    public function countDistinctActiveThemes(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT IDENTITY(r.theme))')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve tous les red flags d'un thème, incluant les archivés.
     * Tri par rareté (common → rare → legendary) puis alphabétique.
     *
     * @return RedFlag[]
     */
    public function findAllByThemeIncludingArchived(Theme $theme): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            // Doctrine ne sait pas trier sur l'enum string directement.
            // On utilise CASE WHEN pour avoir un ordre custom.
            return $this->createQueryBuilder('r')
                ->where('r.theme = :theme')
                ->setParameter('theme', $theme)
                ->addSelect("CASE r.rarity
                    WHEN 'common' THEN 0
                    WHEN 'rare' THEN 1
                    WHEN 'legendary' THEN 2
                    ELSE 99
                END AS HIDDEN rarity_order")
                ->orderBy('rarity_order', 'ASC')
                ->addOrderBy('r.text', 'ASC')
                ->getQuery()
                ->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }
    }

    /**
     * Trouve les red flags archivés depuis plus de N jours.
     * Bypass le filtre car il faut justement voir les archivés.
     *
     * @return RedFlag[]
     */
    public function findArchivedOlderThan(int $days): array
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            return $this->createQueryBuilder('r')
                ->where('r.archivedAt IS NOT NULL')
                ->andWhere('r.archivedAt < :threshold')
                ->setParameter('threshold', $threshold)
                ->getQuery()
                ->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }
    }

    /**
     * Trouve des red flags par leurs IDs, en incluant les archivés.
     * Utilisé pour l'affichage des cartes existantes (qui doivent rester fonctionnelles
     * même si certains red flags ont été archivés depuis).
     *
     * @param int[] $ids
     * @return RedFlag[]
     */
    public function findByIdsIncludingArchived(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            return $this->createQueryBuilder('r')
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }
    }

    /**
     * Trouve un red flag par son ID, en incluant les archivés.
     * Utilisé pour l'affichage d'une cellule unique (route toggle).
     */
    public function findIncludingArchived(int $id): ?RedFlag
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            return $this->find($id);
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }
    }
}
