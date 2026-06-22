<?php

namespace App\Service;

use App\Entity\BingoCard;
use App\Entity\RedFlag;
use App\Entity\Theme;
use App\Enum\Rarity;
use App\Repository\BingoCardRepository;
use App\Repository\RedFlagRepository;
use Doctrine\ORM\EntityManagerInterface;

class CardGenerator
{
    private const CELLS_PER_CARD = 25;
    private const DISTRIBUTION   = [
        'common'    => 15,
        'legendary' => 3,
        'rare'      => 7,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedFlagRepository $redFlagRepository,
        private readonly BingoCardRepository $bingoCardRepository,
    ) {
    }

    public function generate(Theme $theme): BingoCard
    {
        $pickedIds = [];

        // 1. Pour chaque rareté, on pioche le nombre voulu de red flags
        foreach (self::DISTRIBUTION as $rarityValue => $count) {
            $rarity = Rarity::from($rarityValue);

            $pool = $this->redFlagRepository->findBy([
                'theme'  => $theme,
                'rarity' => $rarity,
            ]);

            if (count($pool) < $count) {
                throw new \RuntimeException(sprintf(
                    'Pas assez de red flags %s dans le thème "%s" (besoin: %d, dispo: %d)',
                    $rarity->label(),
                    $theme->getName(),
                    $count,
                    count($pool),
                ));
            }

            // Mélange + prend les N premiers
            shuffle($pool);
            $picked = array_slice($pool, 0, $count);

            foreach ($picked as $redFlag) {
                $pickedIds[] = $redFlag->getId();
            }
        }

        // 2. Mélange final pour disperser les raretés sur la grille
        shuffle($pickedIds);

        // 3. Création de la BingoCard
        $card = new BingoCard();
        $card->setSlug($this->generateUniqueSlug());
        $card->setTheme($theme);
        $card->setCells($pickedIds);
        $card->setMarkedCells([]);
        // createdAt est rempli automatiquement via #[ORM\PrePersist]

        $this->em->persist($card);
        $this->em->flush();

        return $card;
    }

    /**
     * Génère un slug court de 7 caractères (lettres minuscules + chiffres),
     * sans caractères ambigus (pas de 0/o/l/i/1).
     */
    private function generateUniqueSlug(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $slug = '';
            for ($i = 0; $i < 7; $i++) {
                $slug .= $chars[random_int(0, strlen($chars) - 1)];
            }

            if (!$this->bingoCardRepository->findOneBy(['slug' => $slug])) {
                return $slug;
            }
        }

        throw new \RuntimeException('Impossible de générer un slug unique après 10 tentatives');
    }
}
