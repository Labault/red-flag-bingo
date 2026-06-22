<?php

namespace App\Controller\Admin;

use App\Entity\Theme;
use App\Form\Admin\ThemeType;
use App\Repository\ThemeRepository;
use App\Service\Export\ThemeExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes')]
final class ThemeAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ThemeRepository $themeRepository,
        private readonly ThemeExporter $exporter,
    ) {}

    #[Route('/{slug}/delete', name: 'app_admin_themes_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        Request $request
    ): Response
    {
        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('delete_theme_' . $theme->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_themes_index');
        }

        $name = $theme->getName();
        $this->em->remove($theme);
        $this->em->flush();

        $this->addFlash('success', sprintf('🗑️ Thème "%s" supprimé (red flags et cartes inclus).', $name));

        return $this->redirectToRoute('app_admin_themes_index');
    }

    #[Route('/{slug}/edit', name: 'app_admin_themes_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme,
        Request $request,
    ): Response
    {
        $form = $this->createForm(ThemeType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', sprintf('✨ Thème "%s" mis à jour.', $theme->getName()));

            return $this->redirectToRoute('app_admin_themes_index');
        }

        return $this->render('admin/themes/edit.html.twig', [
            'form'  => $form,
            'theme' => $theme,
        ]);
    }

    #[Route('/{slug}/export', name: 'app_admin_themes_export', methods: ['GET'])]
    public function export(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Theme $theme
    ): Response
    {
        $yaml = $this->exporter->exportToYaml($theme);
        $filename = $this->exporter->getFilename($theme);

        $response = new Response($yaml);
        $response->headers->set('Content-Type', 'application/yaml; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }

    #[Route('', name: 'app_admin_themes_index', methods: ['GET'])]
    public function index(): Response
    {
        $themes = $this->themeRepository->findAllOrderedByName();
        $stats = $this->themeRepository->getStatsForThemes($themes);

        return $this->render('admin/themes/index.html.twig', [
            'stats'  => $stats,
            'themes' => $themes,
        ]);
    }
}
