<?php

namespace App\Controller\Admin;

use App\Service\Stats\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $this->statsService->getAllStats(),
        ]);
    }
}
