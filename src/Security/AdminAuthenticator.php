<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Authenticator simple : compare le mot de passe posté au hash stocké en .env.
 * Aucune entité User, juste un InMemoryUser virtuel avec ROLE_ADMIN.
 */
final class AdminAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const ADMIN_USERNAME = 'admin';
    public const LOGIN_ROUTE    = 'app_admin_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
        private readonly RateLimiterFactory $adminLoginLimiter,
        private readonly string $adminPasswordHash,
    ) {}

    public function authenticate(Request $request): Passport
    {
        // Rate limiting par IP
        $limiter = $this->adminLoginLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(
                900,
                'Trop de tentatives. Réessaie dans 15 minutes.'
            );
        }

        $password  = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if ('' === $password) {
            throw new CustomUserMessageAuthenticationException('Mot de passe requis.');
        }

        if ('' === $this->adminPasswordHash) {
            throw new CustomUserMessageAuthenticationException(
                'Hash admin non configuré. Lance: bin/console app:admin:hash-password'
            );
        }

        // On vérifie le password manuellement contre le hash de .env
        $hasher = $this->hasherFactory->getPasswordHasher('admin');
        if (!$hasher->verify($this->adminPasswordHash, $password)) {
            throw new CustomUserMessageAuthenticationException('Mot de passe incorrect.');
        }

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            self::ADMIN_USERNAME
        );

        // Reset du rate limiter en cas de succès
        $limiter->reset();

        // Le user est résolu via le provider in_memory configuré dans security.yaml
        return new SelfValidatingPassport(
            new UserBadge(self::ADMIN_USERNAME),
            [new CsrfTokenBadge('admin_login', $csrfToken)]
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && self::LOGIN_ROUTE === $request->attributes->get('_route');
    }
}
