<?php

namespace App\Doctrine\Filter;

use App\Entity\RedFlag;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Exclut automatiquement les RedFlag archivés des requêtes Doctrine.
 *
 * Activé par défaut. Désactivable temporairement via :
 *   $em->getFilters()->disable('archived_red_flag');
 */
final class ArchivedRedFlagFilter extends SQLFilter
{
    /**
     * @param string $targetTableAlias
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (RedFlag::class !== $targetEntity->getName()) {
            return '';
        }

        return sprintf('%s.archived_at IS NULL', $targetTableAlias);
    }
}
