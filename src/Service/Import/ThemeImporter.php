<?php

namespace App\Service\Import;

use App\Dto\Import\RedFlagImportDto;
use App\Dto\Import\ThemeImportDto;
use App\Entity\RedFlag;
use App\Entity\Theme;
use App\Enum\Rarity;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Import d'un thème + ses red flags depuis un fichier YAML.
 *
 * Utilisable en mode dry-run (validation sans insertion) ou en mode réel.
 * Les red flags dont le `text` existe déjà dans le thème sont skippés silencieusement
 * (y compris ceux archivés, pour éviter les doublons après restauration).
 */
final class ThemeImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ThemeRepository $themeRepository,
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Construit les DTOs à partir des données YAML brutes.
     */
    private function buildDto(array $data): ThemeImportDto
    {
        $themeData    = $data['theme'] ?? [];
        $redFlagsData = $data['red_flags'] ?? [];

        $dto = new ThemeImportDto();
        $dto->name  = isset($themeData['name']) ? trim((string) $themeData['name']) : null;
        $dto->slug  = isset($themeData['slug']) ? trim((string) $themeData['slug']) : null;
        $dto->emoji = isset($themeData['emoji']) ? trim((string) $themeData['emoji']) : null;

        if (is_array($redFlagsData)) {
            foreach ($redFlagsData as $rfData) {
                if (!is_array($rfData)) {
                    continue;
                }
                $rfDto = new RedFlagImportDto();
                $rfDto->text     = isset($rfData['text']) ? trim((string) $rfData['text']) : null;
                $rfDto->rarity   = $this->parseRarity($rfData['rarity'] ?? null);
                $dto->redFlags[] = $rfDto;
            }
        }

        return $dto;
    }

    /**
     * Compte les red flags actifs (non archivés) d'un thème, groupés par rareté.
     * Le filtre 'archived_red_flag' s'applique automatiquement → seuls les actifs comptent.
     *
     * @return array{common: int, rare: int, legendary: int}
     */
    private function countActiveByRarity(Theme $theme): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('r.rarity', 'COUNT(r.id) AS cnt')
            ->from(RedFlag::class, 'r')
            ->where('r.theme = :theme')
            ->setParameter('theme', $theme)
            ->groupBy('r.rarity')
            ->getQuery()
            ->getScalarResult();

        $counts = ['common' => 0, 'legendary' => 0, 'rare' => 0];
        foreach ($rows as $row) {
            $rarityValue = $row['rarity'] instanceof Rarity ? $row['rarity']->value : (string) $row['rarity'];
            if (isset($counts[$rarityValue])) {
                $counts[$rarityValue] = (int) $row['cnt'];
            }
        }

        return $counts;
    }

    /**
     * Récupère les textes des red flags existants (en lowercase, incluant archivés).
     * On désactive le filtre Doctrine pour ne pas créer de doublon si un red flag
     * archivé porte le même texte.
     *
     * @return string[]
     */
    private function getExistingRedFlagTexts(Theme $theme): array
    {
        $filters = $this->em->getFilters();
        $wasEnabled = $filters->isEnabled('archived_red_flag');
        if ($wasEnabled) {
            $filters->disable('archived_red_flag');
        }

        try {
            $rows = $this->em->createQueryBuilder()
                ->select('r.text')
                ->from(RedFlag::class, 'r')
                ->where('r.theme = :theme')
                ->setParameter('theme', $theme)
                ->getQuery()
                ->getScalarResult();

            return array_map(
                fn ($row) => mb_strtolower($row['text']),
                $rows
            );
        } finally {
            if ($wasEnabled) {
                $filters->enable('archived_red_flag');
            }
        }
    }

    public function import(string $yamlContent, bool $dryRun = true): ImportReport
    {
        $report = new ImportReport();
        $report->isDryRun = $dryRun;

        // 1. Parsing YAML
        try {
            $data = Yaml::parse($yamlContent);
        } catch (ParseException $e) {
            $report->validationErrors[] = [
                'message' => 'YAML invalide : ' . $e->getMessage(),
                'path'    => 'yaml',
            ];
            return $report;
        }

        if (!is_array($data) || !isset($data['theme']) || !isset($data['red_flags'])) {
            $report->validationErrors[] = [
                'message' => 'Le YAML doit contenir une clé "theme" et une clé "red_flags".',
                'path'    => 'structure',
            ];
            return $report;
        }

        // 2. Construction des DTOs
        $dto = $this->buildDto($data);

        // 3. Validation
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $report->validationErrors[] = [
                    'message' => $violation->getMessage(),
                    'path'    => $violation->getPropertyPath(),
                ];
            }
            return $report;
        }

        // 4. Slug fallback
        $slug = $dto->slug ?: strtolower((string) $this->slugger->slug($dto->name));

        $report->themeName  = $dto->name;
        $report->themeSlug  = $slug;
        $report->themeEmoji = $dto->emoji;

        // 5. Theme existant ou nouveau ?
        $theme = $this->themeRepository->findOneBy(['slug' => $slug]);
        $report->themeAlreadyExists = null !== $theme;
        $report->themeWillBeCreated = null === $theme;

        if (!$dryRun && !$theme) {
            $theme = new Theme();
            $theme->setName($dto->name);
            $theme->setSlug($slug);
            $theme->setEmoji($dto->emoji);
            $this->em->persist($theme);
        }

        // 6. Compte les red flags actifs déjà présents (par rareté) pour la jouabilité
        // Uniquement si le thème existait déjà (sinon il n'a évidemment aucun red flag)
        $isExistingTheme = null !== $theme && null !== $theme->getId();

        if ($isExistingTheme) {
            $report->existingActiveByRarity = $this->countActiveByRarity($theme);
        }

        // 7. Récupération des textes existants pour skip (incluant archivés)
        // Idem : pas la peine d'interroger la BDD pour un thème qu'on vient de créer
        $existingTexts = $isExistingTheme ? $this->getExistingRedFlagTexts($theme) : [];

        // 8. Traitement de chaque red flag
        foreach ($dto->redFlags as $rfDto) {
            $normalizedText = trim($rfDto->text);

            if (in_array(mb_strtolower($normalizedText), $existingTexts, true)) {
                $report->skippedRedFlags[] = [
                    'reason' => 'Un red flag avec ce texte existe déjà dans le thème.',
                    'text'   => $normalizedText,
                ];
                continue;
            }

            $report->createdRedFlags[] = [
                'rarity' => $rfDto->rarity->value,
                'text'   => $normalizedText,
            ];

            if (!$dryRun) {
                $redFlag = new RedFlag();
                $redFlag->setText($normalizedText);
                $redFlag->setRarity($rfDto->rarity);
                $redFlag->setTheme($theme);
                $this->em->persist($redFlag);

                $existingTexts[] = mb_strtolower($normalizedText);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
            $this->logger->info('Import YAML effectué', [
                'created' => $report->totalCreated(),
                'skipped' => $report->totalSkipped(),
                'theme'   => $slug,
            ]);
        }

        return $report;
    }

    private function parseRarity(mixed $value): ?Rarity
    {
        if (!is_string($value)) {
            return null;
        }
        return Rarity::tryFrom(strtolower(trim($value)));
    }
}
