<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Form login authenticator: username + password ile giris yapar,
 * basari durumunda MyBB uyumlu 'mybbuser' cookie'sini set eder.
 */
final class MyBBLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MyBBPasswordHasher $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/login'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $username = trim($request->request->getString('_username'));
        $password = $request->request->getString('_password');

        if ($username === '' || $password === '') {
            throw new CustomUserMessageAuthenticationException('Kullanici adi ve sifre gereklidir.');
        }

        return new SelfValidatingPassport(
            new UserBadge($username, function (string $identifier) use ($password): User {
                $user = $this->userRepository->findOneBy(['username' => $identifier]);

                if ($user === null) {
                    throw new CustomUserMessageAuthenticationException('Kullanici bulunamadi.');
                }

                if (!$this->passwordHasher->verify($user->getPassword(), $password, $user->getSalt())) {
                    throw new CustomUserMessageAuthenticationException('Sifre yanlis.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        $response = new RedirectResponse($this->urlGenerator->generate('app_board_index'));

        // MyBB uyumlu cookie: uid_loginkey
        $cookieValue = $user->getUid() . '_' . $user->getLoginkey();
        $response->headers->setCookie(
            Cookie::create('mybbuser')
                ->withValue($cookieValue)
                ->withExpires(time() + 60 * 60 * 24 * 30)
                ->withPath('/')
                ->withHttpOnly(true)
        );

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('_security.last_error', $exception);
        $request->getSession()->set('_security.last_username', $request->request->getString('_username'));

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
