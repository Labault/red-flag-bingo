<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'app_admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, on redirige vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('admin/security/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'app_admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Interceptée par le firewall (logout: path: app_admin_logout)
        throw new \LogicException('Cette méthode ne devrait jamais être appelée.');
    }
}
