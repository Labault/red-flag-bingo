<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(
    name: 'app:admin:hash-password',
    description: 'Génère un hash bcrypt du mot de passe admin à coller dans .env.local',
)]
final class AdminHashPasswordCommand extends Command
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔐 Hash de mot de passe admin');

        $question = new Question('Mot de passe admin : ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator(function (mixed $value): string {
            if (!is_string($value) || strlen($value) < 8) {
                throw new \RuntimeException('Le mot de passe doit faire au moins 8 caractères.');
            }
            return $value;
        });

        $password = $io->askQuestion($question);
        if (!is_string($password)) {
            $io->error('Mot de passe invalide.');
            return Command::FAILURE;
        }

        $hasher = $this->hasherFactory->getPasswordHasher('admin');
        $hash = $hasher->hash($password);

        $io->success('Hash généré ! Copie cette ligne dans ton .env.local :');
        $io->writeln('');
        $io->writeln('<comment>ADMIN_PASSWORD_HASH=' . $hash . '</comment>');
        $io->writeln('');
        $io->note('Le mot de passe lui-même n\'est pas stocké, seulement son hash.');

        return Command::SUCCESS;
    }
}
