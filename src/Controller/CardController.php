<?php

namespace App\Controller;

use App\Entity\BingoCard;
use App\Repository\RedFlagRepository;
use App\Repository\ThemeRepository;
use App\Service\BingoChecker;
use App\Service\CardGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class CardController extends AbstractController
{
    private function countActiveViewers(string $slug, CacheItemPoolInterface $cache): int
    {
        $item = $cache->getItem('viewers_' . $slug);
        $viewers = $item->isHit() ? $item->get() : [];
        /** @var array<string, int> $viewers */
        $viewers = is_array($viewers) ? $viewers : [];

        // Filtre les viewers expirés (heartbeat plus ancien que 15s)
        $now = time();
        $viewers = array_filter($viewers, fn (int $lastSeen): bool => ($now - $lastSeen) < 15);

        return count($viewers);
    }

    #[Route('/card/{slug}/heartbeat', name: 'app_card_heartbeat', methods: ['POST'])]
    public function heartbeat(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        BingoCard $card,
        Request $request,
        CacheItemPoolInterface $cache,
        HubInterface $hub,
        Environment $twig,
    ): Response {
        $viewerId = $request->request->getString('viewerId');
        if ('' === $viewerId) {
            return new JsonResponse(['ok' => false], 400);
        }

        // Récupère la liste des viewers
        $item = $cache->getItem('viewers_' . $card->getSlug());
        $viewers = $item->isHit() ? $item->get() : [];
        /** @var array<string, int> $viewers */
        $viewers = is_array($viewers) ? $viewers : [];

        // Met à jour le timestamp de ce viewer
        $viewers[$viewerId] = time();

        // Nettoie les viewers expirés
        $now = time();
        $viewers = array_filter($viewers, fn (int $lastSeen): bool => ($now - $lastSeen) < 15);

        // Sauvegarde
        $item->set($viewers);
        $item->expiresAfter(60); // au cas où la carte est abandonnée
        $cache->save($item);

        $viewerCount = count($viewers);

        // Broadcast du nouveau compteur via Turbo Stream
        $countHtml = $twig->render('card/_viewer_count.html.twig', [
            'viewerCount' => $viewerCount,
        ]);

        $stream = sprintf(
            '<turbo-stream action="replace" target="viewer-count"><template>%s</template></turbo-stream>',
            $countHtml
        );

        $hub->publish(new Update(
            'https://rfb.app/cards/' . $card->getSlug(),
            $stream,
        ));

        return new JsonResponse(['count' => $viewerCount, 'ok' => true]);
    }

    #[Route('/card/new/{themeSlug}', name: 'app_card_new', methods: ['POST'])]
    public function new(
        string $themeSlug,
        ThemeRepository $themeRepository,
        CardGenerator $cardGenerator,
    ): Response {
        $theme = $themeRepository->findOneBy(['slug' => $themeSlug]);

        if (!$theme) {
            throw $this->createNotFoundException(sprintf('Thème "%s" introuvable', $themeSlug));
        }

        $card = $cardGenerator->generate($theme);

        return $this->redirectToRoute('app_card_show', ['slug' => $card->getSlug()]);
    }

    #[Route('/card/{slug}', name: 'app_card_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        BingoCard $card,
        RedFlagRepository $redFlagRepository,
        BingoChecker $bingoChecker,
        CacheItemPoolInterface $cache,
    ): Response {
        // On charge tous les red flags de la carte en une seule requête
        $redFlags = $redFlagRepository->findByIdsIncludingArchived($card->getCells());

        $redFlagsById = [];
        foreach ($redFlags as $rf) {
            $redFlagsById[(int) $rf->getId()] = $rf;
        }

        // Calcul des positions gagnantes (cases d'au moins une ligne complète)
        $winningPositions = $bingoChecker->getWinningPositions($card->getMarkedCells());
        $winningLines = $bingoChecker->getWinningLines($card->getMarkedCells());

        // On reconstruit la grille dans l'ordre stocké en DB
        $cells = [];
        foreach ($card->getCells() as $position => $redFlagId) {
            $cells[] = [
                'isMarked'  => in_array($position, $card->getMarkedCells(), true),
                'isWinning' => in_array($position, $winningPositions, true),
                'position'  => $position,
                'redFlag'   => $redFlagsById[$redFlagId] ?? null,
            ];
        }

        // Compte les viewers actifs (clé "viewers:{slug}" en cache)
        $viewerCount = $this->countActiveViewers($card->getSlug(), $cache);

        return $this->render('card/show.html.twig', [
            'bingoCount'  => count($winningLines),
            'card'        => $card,
            'cells'       => $cells,
            'hasBingo'    => count($winningLines) > 0,
            'viewerCount' => $viewerCount,
        ]);
    }

    #[Route('/card/{slug}/toggle/{position}', name: 'app_card_toggle', methods: ['POST'], requirements: ['position' => '\d+'])]
    public function toggle(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        BingoCard $card,
        int $position,
        EntityManagerInterface $em,
        RedFlagRepository $redFlagRepository,
        BingoChecker $bingoChecker,
        HubInterface $hub,
        Environment $twig,
    ): Response {
        // Validation : position doit être entre 0 et 24
        if ($position < 0 || $position > 24) {
            throw $this->createNotFoundException('Position invalide');
        }

        $markedBefore = $card->getMarkedCells();
        $marked       = $markedBefore;

        if (in_array($position, $marked, true)) {
            // Déjà marqué → on décoche
            $marked = array_values(array_filter($marked, fn ($p) => $p !== $position));
        } else {
            // Pas marqué → on coche
            $marked[] = $position;
        }

        $card->setMarkedCells($marked);

        // Détection du premier bingo : on stocke la date la première fois qu'on en atteint un
        $winningLinesNow = $bingoChecker->getWinningLines($marked);
        if (count($winningLinesNow) > 0 && !$card->hasReachedBingo()) {
            $card->setBingoReachedAt(new \DateTimeImmutable());
        } elseif (0 === count($winningLinesNow) && $card->hasReachedBingo()) {
            // Si l'utilisateur dé-coche et n'a plus aucun bingo → on annule
            $card->setBingoReachedAt(null);
        }

        $em->flush();

        // 🚀 Publication Mercure : on broadcast toutes les cases dont l'affichage change
        $winningPositionsBefore = $bingoChecker->getWinningPositions($markedBefore);
        $winningPositions       = $bingoChecker->getWinningPositions($marked);
        $winningLines           = $winningLinesNow;

        // On rafraîchit la case togglée + toutes celles dont l'état "gagnante" bascule
        // (sinon, sur un bingo qui complète une ligne, seule la dernière case passe en doré)
        $positionsToPublish = array_values(array_unique(array_merge(
            [$position],
            array_diff($winningPositions, $winningPositionsBefore),
            array_diff($winningPositionsBefore, $winningPositions),
        )));

        $redFlagIds   = array_values(array_filter(array_map(
            fn (int $pos) => $card->getCells()[$pos] ?? null,
            $positionsToPublish,
        )));
        $redFlagsById = [];
        foreach ($redFlagRepository->findByIdsIncludingArchived($redFlagIds) as $rf) {
            $redFlagsById[(int) $rf->getId()] = $rf;
        }

        foreach ($positionsToPublish as $pos) {
            $redFlagId = $card->getCells()[$pos] ?? null;

            $cell = [
                'isMarked'  => in_array($pos, $marked, true),
                'isWinning' => in_array($pos, $winningPositions, true),
                'position'  => $pos,
                'redFlag'   => $redFlagId ? ($redFlagsById[$redFlagId] ?? null) : null,
            ];

            $cellHtml = $twig->render('card/_cell.html.twig', [
                'card' => $card,
                'cell' => $cell,
            ]);

            $turboStream = sprintf(
                '<turbo-stream action="replace" target="cell-%d"><template>%s</template></turbo-stream>',
                $pos,
                $cellHtml
            );

            $hub->publish(new Update(
                'https://rfb.app/cards/' . $card->getSlug(),
                $turboStream,
            ));
        }

        // 🚀 Publication du bandeau bingo (toujours, même quand vide → permet de le cacher si on décoche)
        $bannerHtml = $twig->render('card/_bingo_banner.html.twig', [
            'bingoCount' => count($winningLines),
            'hasBingo'   => count($winningLines) > 0,
        ]);

        $bannerStream = sprintf(
            '<turbo-stream action="replace" target="bingo-banner"><template>%s</template></turbo-stream>',
            $bannerHtml
        );

        $hub->publish(new Update(
            'https://rfb.app/cards/' . $card->getSlug(),
            $bannerStream,
        ));

        return $this->redirectToRoute('app_card_show', ['slug' => $card->getSlug()]);
    }
}
