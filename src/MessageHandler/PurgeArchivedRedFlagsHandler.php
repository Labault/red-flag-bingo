<?php

namespace App\MessageHandler;

use App\Message\PurgeArchivedRedFlagsMessage;
use App\Service\ArchiveService;
use App\Service\Stats\StatsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeArchivedRedFlagsHandler
{
    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly StatsService $statsService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(PurgeArchivedRedFlagsMessage $message): void
    {
        $this->logger->info('Démarrage de la purge des red flags archivés', [
            'threshold' => $message->daysThreshold,
        ]);

        $count = $this->archiveService->purgeOlderThan($message->daysThreshold);

        if ($count > 0) {
            // Les stats du dashboard ne sont plus à jour, on invalide le cache
            $this->statsService->invalidateCache();
        }

        $this->logger->info('Purge terminée', ['purged_count' => $count]);
    }
}
