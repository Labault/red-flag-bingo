<?php

namespace App\Command;

use App\Repository\BingoCardRepository;
use App\Service\BingoChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill:bingo-reached-at',
    description: 'Calcule et stocke bingoReachedAt pour les cartes existantes ayant déjà atteint un bingo.',
)]
final class BackfillBingoReachedAtCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BingoCardRepository $cardRepository,
        private readonly BingoChecker $bingoChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans rien modifier.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Backfill bingoReachedAt sur les cartes existantes');

        $cards = $this->cardRepository->findAll();
        $io->writeln(sprintf('  • %d carte(s) à analyser', count($cards)));

        $updated    = 0;
        $alreadySet = 0;
        $noBingo    = 0;

        foreach ($cards as $card) {
            if ($card->hasReachedBingo()) {
                $alreadySet++;
                continue;
            }

            $winningLines = $this->bingoChecker->getWinningLines($card->getMarkedCells());

            if (0 === count($winningLines)) {
                $noBingo++;
                continue;
            }

            // On utilise createdAt comme approximation : on ne sait pas exactement quand
            // le bingo a été atteint, mais c'est forcément après la création de la carte.
            if (!$dryRun) {
                $card->setBingoReachedAt($card->getCreatedAt());
            }
            $updated++;
        }

        if (!$dryRun && $updated > 0) {
            $this->em->flush();
        }

        $io->section('Résultat');
        $io->writeln(sprintf('  ✅ %d carte(s) mise(s) à jour', $updated));
        $io->writeln(sprintf('  ⏭️  %d carte(s) sans bingo', $noBingo));
        $io->writeln(sprintf('  🟢 %d carte(s) déjà à jour', $alreadySet));

        if ($dryRun) {
            $io->note('Aucune modification (dry-run).');
        } else {
            $io->success('Backfill terminé !');
        }

        return Command::SUCCESS;
    }
}
