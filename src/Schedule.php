<?php

namespace App;

use App\Message\PurgeArchivedRedFlagsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)            // rejoue les tâches manquées au redémarrage
            ->processOnlyLastMissedRun(true)    // évite la tempête si plusieurs runs ont été ratés

            // 🗑️ Purge des red flags archivés depuis +90 jours
            ->add(
                RecurringMessage::cron(
                    '0 3 * * *',  // tous les jours à 3h00
                    new PurgeArchivedRedFlagsMessage(daysThreshold: 90)
                )
            )
        ;
    }
}
