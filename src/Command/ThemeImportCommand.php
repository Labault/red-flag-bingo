<?php

namespace App\Command;

use App\Service\Import\ThemeImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:theme:import',
    description: 'Importe un thème + ses red flags depuis un fichier YAML.',
)]
final class ThemeImportCommand extends Command
{
    public function __construct(
        private readonly ThemeImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier YAML à importer.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide le YAML sans rien persister.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file   = $input->getArgument('file');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('Fichier introuvable ou illisible : %s', $file));
            return Command::FAILURE;
        }

        $io->title(sprintf('Import du fichier : %s%s', basename($file), $dryRun ? ' (DRY-RUN)' : ''));

        $yaml = file_get_contents($file);
        $report = $this->importer->import($yaml, dryRun: $dryRun);

        // Erreurs de validation
        if ($report->hasErrors()) {
            $io->error('Erreurs de validation :');
            foreach ($report->validationErrors as $error) {
                $io->writeln(sprintf('  • <fg=red>[%s]</> %s', $error['path'], $error['message']));
            }
            return Command::FAILURE;
        }

        // Récap thème
        $io->section('Thème');
        $io->definitionList(
            ['Nom'   => $report->themeName],
            ['Slug'  => $report->themeSlug],
            ['Emoji' => $report->themeEmoji],
            ['État'  => $report->themeAlreadyExists ? '🔁 Existant (red flags ajoutés)' : '🆕 Sera créé'],
        );

        // Récap red flags
        $io->section('Red flags');
        $io->writeln(sprintf('  ✅ <info>%d</info> à créer', $report->totalCreated()));
        $io->writeln(sprintf('  ⏭️  <comment>%d</comment> skippés (déjà existants)', $report->totalSkipped()));

        // Jouabilité
        $playability = $report->isPlayable();
        $io->section('Jouabilité (besoin : 15 commons, 7 rares, 3 légendaires)');
        $io->writeln(sprintf('  • Commons    : %d', $playability['byRarity']['common']));
        $io->writeln(sprintf('  • Rares      : %d', $playability['byRarity']['rare']));
        $io->writeln(sprintf('  • Légendaires: %d', $playability['byRarity']['legendary']));

        if (!$playability['playable']) {
            $missing = [];
            foreach ($playability['missing'] as $rarity => $count) {
                $missing[] = sprintf('%d %s', $count, $rarity);
            }
            $io->warning('Thème non jouable. Manquants : ' . implode(', ', $missing));
        } else {
            $io->success('Thème jouable ✅');
        }

        // Skipped détaillés (max 10 pour pas saturer l'output)
        if ($report->totalSkipped() > 0 && $output->isVerbose()) {
            $io->section('Détails des skips');
            foreach (array_slice($report->skippedRedFlags, 0, 10) as $skip) {
                $io->writeln(sprintf('  ⏭️  <comment>%s</comment> — %s', $skip['text'], $skip['reason']));
            }
            if ($report->totalSkipped() > 10) {
                $io->writeln(sprintf('  ... et %d autres', $report->totalSkipped() - 10));
            }
        }

        if ($dryRun) {
            $io->note('Aucune donnée n\'a été persistée (dry-run). Relance sans --dry-run pour importer.');
        } else {
            $io->success('Import terminé !');
        }

        return Command::SUCCESS;
    }
}
