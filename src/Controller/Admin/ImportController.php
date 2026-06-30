<?php

namespace App\Controller\Admin;

use App\Dto\Import\UploadDto;
use App\Form\Admin\UploadType;
use App\Service\Import\ThemeImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ImportController extends AbstractController
{
    public function __construct(
        private readonly ThemeImporter $importer,
        private readonly SluggerInterface $slugger,
        private readonly string $importsDirectory,
    ) {}

    #[Route('/admin/imports', name: 'app_admin_imports_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $dto = new UploadDto();
        $form = $this->createForm(UploadType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Sauvegarde du fichier dans var/imports/ avec timestamp
            $uploadedFile = $dto->file;
            if (!$uploadedFile instanceof UploadedFile) {
                // Garanti non-null par la contrainte NotNull, garde défensive pour le typage.
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('app_admin_imports_index');
            }
            $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName     = $this->slugger->slug($originalName)->lower();
            $filename     = sprintf(
                '%s_%s.%s',
                (new \DateTimeImmutable())->format('Y-m-d_His'),
                $safeName,
                $uploadedFile->getClientOriginalExtension()
            );

            $uploadedFile->move($this->importsDirectory, $filename);

            // 2. Redirection vers la prévisualisation
            return $this->redirectToRoute('app_admin_imports_preview', [
                'filename' => $filename,
            ]);
        }

        return $this->render('admin/imports/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/imports/preview/{filename}', name: 'app_admin_imports_preview', methods: ['GET', 'POST'], requirements: ['filename' => '[\w\-\.]+'])]
    public function preview(string $filename, Request $request): Response
    {
        $path = $this->importsDirectory . '/' . $filename;

        if (!is_file($path) || !is_readable($path)) {
            $this->addFlash('error', 'Fichier introuvable. Réessaie l\'upload.');
            return $this->redirectToRoute('app_admin_imports_index');
        }

        $yaml = file_get_contents($path);
        if (false === $yaml) {
            $this->addFlash('error', 'Fichier illisible. Réessaie l\'upload.');
            return $this->redirectToRoute('app_admin_imports_index');
        }

        // POST = confirmation de l'import réel
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('import_confirm', $token)) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_admin_imports_preview', ['filename' => $filename]);
            }

            $report = $this->importer->import($yaml, dryRun: false);

            if ($report->hasErrors()) {
                $this->addFlash('error', 'Import échoué : erreurs de validation.');
                return $this->redirectToRoute('app_admin_imports_preview', ['filename' => $filename]);
            }

            $this->addFlash('success', sprintf(
                '✨ Import réussi ! %d red flags créés, %d skippés.',
                $report->totalCreated(),
                $report->totalSkipped(),
            ));

            // Redirection après succès (pattern PRG, évite les double-submits et plait à Turbo)
            return $this->redirectToRoute('app_admin_imports_preview', [
                'filename' => $filename,
                'imported' => 1,
            ]);
        }

        // GET = prévisualisation (dry-run) ou affichage post-import
        $report = $this->importer->import($yaml, dryRun: true);
        $imported = (bool) $request->query->get('imported');

        return $this->render('admin/imports/preview.html.twig', [
            'filename' => $filename,
            'imported' => $imported,
            'report'   => $report,
        ]);
    }
}
