<?php

namespace App\Controller;

use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ThemeRepository $themeRepository): Response
    {
        $themes = $themeRepository->findAllOrderedByName();

        return $this->render('home/index.html.twig', [
            'themes' => $themes,
        ]);
    }
}
