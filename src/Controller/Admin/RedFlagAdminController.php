<?php

namespace App\Controller\Admin;

use App\Entity\Theme;
use App\Form\Admin\RedFlagType;
use App\Repository\RedFlagRepository;
use App\Service\ArchiveService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RedFlagAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedFlagRepository $redFlagRepository,
        private readonly ArchiveService $archiveService,
    ) {}

    #[Route('/admin/themes/{slug}/red-flags/{id}/archive', name: 'app_admin_red_flags_archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        int $id,
        Request $request,
    ): Response {
        $redFlag = $this->redFlagRepository->findIncludingArchived($id);
        if (!$redFlag || $redFlag->getTheme()->getId() !== $theme->getId()) {
            throw $this->createNotFoundException('Red flag introuvable.');
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('archive_red_flag_' . $redFlag->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            $this->archiveService->archive($redFlag);
            $this->addFlash('success', '📦 Red flag archivé.');
        }

        return $this->redirectToRoute('app_admin_red_flags_index', [
            'rarity' => $request->query->get('rarity', 'all'),
            'slug'   => $theme->getSlug(),
            'status' => $request->query->get('status', 'active'),
        ]);
    }

    #[Route('/admin/themes/{slug}/red-flags/{id}/edit', name: 'app_admin_red_flags_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        int $id,
        Request $request,
    ): Response {
        $redFlag = $this->redFlagRepository->findIncludingArchived($id);
        if (!$redFlag || $redFlag->getTheme()->getId() !== $theme->getId()) {
            throw $this->createNotFoundException('Red flag introuvable.');
        }

        $form = $this->createForm(RedFlagType::class, $redFlag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', '✨ Red flag mis à jour.');

            return $this->redirectToRoute('app_admin_red_flags_index', [
                'slug' => $theme->getSlug(),
            ]);
        }

        return $this->render('admin/red_flags/edit.html.twig', [
            'form'    => $form,
            'redFlag' => $redFlag,
        ]);
    }

    #[Route('/admin/themes/{slug}/red-flags', name: 'app_admin_red_flags_index', methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        Request $request,
    ): Response {
        $redFlags = $this->redFlagRepository->findAllByThemeIncludingArchived($theme);

        $statusFilter = $request->query->get('status', 'active');
        $rarityFilter = $request->query->get('rarity', 'all');

        $stats = [
            'active'    => 0,
            'archived'  => 0,
            'common'    => 0,
            'legendary' => 0,
            'rare'      => 0,
            'total'     => count($redFlags),
        ];

        $filtered = [];
        foreach ($redFlags as $rf) {
            if ($rf->isArchived()) {
                $stats['archived']++;
            } else {
                $stats['active']++;
            }
            $stats[$rf->getRarity()->value]++;

            if ('active' === $statusFilter && $rf->isArchived()) continue;
            if ('archived' === $statusFilter && !$rf->isArchived()) continue;
            if ('all' !== $rarityFilter && $rf->getRarity()->value !== $rarityFilter) continue;

            $filtered[] = $rf;
        }

        return $this->render('admin/red_flags/index.html.twig', [
            'rarityFilter' => $rarityFilter,
            'redFlags'     => $filtered,
            'stats'        => $stats,
            'statusFilter' => $statusFilter,
            'theme'        => $theme,
        ]);
    }

    #[Route('/admin/themes/{slug}/red-flags/{id}/restore', name: 'app_admin_red_flags_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        int $id,
        Request $request,
    ): Response {
        $redFlag = $this->redFlagRepository->findIncludingArchived($id);
        if (!$redFlag || $redFlag->getTheme()->getId() !== $theme->getId()) {
            throw $this->createNotFoundException('Red flag introuvable.');
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('restore_red_flag_' . $redFlag->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            $this->archiveService->restore($redFlag);
            $this->addFlash('success', '🔄 Red flag restauré.');
        }

        return $this->redirectToRoute('app_admin_red_flags_index', [
            'rarity' => $request->query->get('rarity', 'all'),
            'slug'   => $theme->getSlug(),
            'status' => $request->query->get('status', 'archived'),
        ]);
    }
}
