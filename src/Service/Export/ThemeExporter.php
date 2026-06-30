<?php

namespace App\Service\Export;

use App\Entity\RedFlag;
use App\Entity\Theme;
use App\Repository\RedFlagRepository;
use Symfony\Component\Yaml\Yaml;

/**
 * Exporte un thème + ses red flags **actifs** au format YAML compatible avec ThemeImporter.
 * Les red flags archivés sont exclus volontairement (pas de sens de les versionner).
 */
final class ThemeExporter
{
    private const RARITY_ORDER = ['common' => 0, 'legendary' => 2, 'rare' => 1];

    public function __construct(
        private readonly RedFlagRepository $redFlagRepository,
    ) {}

    public function exportToYaml(Theme $theme): string
    {
        // Le filtre 'archived_red_flag' est actif → on récupère uniquement les actifs
        $redFlags = $this->redFlagRepository->findBy(['theme' => $theme]);

        // Tri par rareté puis alphabétique pour un fichier lisible
        usort($redFlags, function (RedFlag $a, RedFlag $b): int {
            $rarityCmp = self::RARITY_ORDER[$a->getRarity()->value]
                       <=> self::RARITY_ORDER[$b->getRarity()->value];

            if (0 !== $rarityCmp) {
                return $rarityCmp;
            }

            return strcmp($a->getText(), $b->getText());
        });

        $data = [
            'red_flags' => array_map(fn ($rf) => [
                'rarity' => $rf->getRarity()->value,
                'text'   => $rf->getText(),
            ], $redFlags),
            'theme'     => [
                'emoji' => $theme->getEmoji(),
                'name'  => $theme->getName(),
                'slug'  => $theme->getSlug(),
            ],
        ];

        // Génération YAML : 4 niveaux d'indent, multi-line, conserve les apostrophes
        return Yaml::dump(
            $data,
            inline: 4,
            indent: 2,
            flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );
    }

    /**
     * Génère un nom de fichier propre pour le téléchargement.
     */
    public function getFilename(Theme $theme): string
    {
        return sprintf('%s.yaml', $theme->getSlug());
    }
}
