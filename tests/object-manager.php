<?php

// Boots the Symfony kernel and hands the Doctrine EntityManager to
// phpstan-doctrine so it can infer DQL / QueryBuilder result types.

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$appEnv = is_string($_SERVER['APP_ENV'] ?? null) ? $_SERVER['APP_ENV'] : 'dev';
$kernel = new Kernel($appEnv, (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
assert($doctrine instanceof ManagerRegistry);

return $doctrine->getManager();
