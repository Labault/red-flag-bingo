<?php

namespace App\Command;

use App\Message\PurgeArchivedRedFlagsMessage;
use App\Service\ArchiveService;
use App\Service\Stats\StatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge:archived-red-flags',
    description: 'Supprime définitivement les red flags archivés depuis plus de N jours.',
)]
final class PurgeArchivedRedFlagsCommand extends Command
{
    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly StatsService $statsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Seuil en jours.', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans rien toucher.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysOption = $input->getOption('days');
        $days   = is_numeric($daysOption) ? (int) $daysOption : 90;
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title(sprintf('Purge des red flags archivés depuis plus de %d jours', $days));

        if ($dryRun) {
            $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
            $io->note(sprintf('Mode dry-run : seuil = avant %s', $threshold->format('d/m/Y H:i')));
            $io->warning('Ce mode ne supprime rien. Lance sans --dry-run pour exécuter.');
            return Command::SUCCESS;
        }

        $count = $this->archiveService->purgeOlderThan($days);

        if ($count > 0) {
            $this->statsService->invalidateCache();
        }

        $io->success(sprintf(
            '%s red flag(s) supprimé(s).',
            $count
        ));

        return Command::SUCCESS;
    }
}
