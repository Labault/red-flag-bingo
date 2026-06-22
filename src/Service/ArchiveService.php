<?php

namespace App\Service;

use App\Entity\RedFlag;
use App\Repository\RedFlagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Centralise la logique d'archivage et de restauration des red flags.
 */
final class ArchiveService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedFlagRepository $redFlagRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Archive un red flag (soft-delete). Idempotent.
     */
    public function archive(RedFlag $redFlag): void
    {
        if ($redFlag->isArchived()) {
            return;
        }

        $redFlag->setArchivedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('Red flag archivé', [
            'id'   => $redFlag->getId(),
            'text' => $redFlag->getText(),
        ]);
    }

    /**
     * Supprime définitivement les red flags archivés depuis plus de N jours.
     * Retourne le nombre d'entrées purgées.
     */
    public function purgeOlderThan(int $days): int
    {
        $redFlags = $this->redFlagRepository->findArchivedOlderThan($days);
        $count = count($redFlags);

        if (0 === $count) {
            return 0;
        }

        foreach ($redFlags as $redFlag) {
            $this->em->remove($redFlag);
        }

        $this->em->flush();

        $this->logger->info('Purge des red flags archivés effectuée', [
            'count'          => $count,
            'days_threshold' => $days,
        ]);

        return $count;
    }

    /**
     * Restaure un red flag archivé.
     */
    public function restore(RedFlag $redFlag): void
    {
        if (!$redFlag->isArchived()) {
            return;
        }

        $redFlag->setArchivedAt(null);
        $this->em->flush();

        $this->logger->info('Red flag restauré', [
            'id'   => $redFlag->getId(),
            'text' => $redFlag->getText(),
        ]);
    }
}
