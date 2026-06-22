<?php

namespace App\Message;

/**
 * Message déclenché quotidiennement par le scheduler pour purger
 * les red flags archivés depuis plus de N jours.
 */
final class PurgeArchivedRedFlagsMessage
{
    public function __construct(
        public readonly int $daysThreshold = 90,
    ) {}
}
