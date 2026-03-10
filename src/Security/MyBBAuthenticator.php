<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class MyBBAuthenticator extends AbstractAuthenticator
{
    private const string COOKIE_NAME = 'mybbuser';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has(self::COOKIE_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $cookie = (string) $request->cookies->get(self::COOKIE_NAME);
        $parts = explode('_', $cookie, 2);

        if (count($parts) !== 2) {
            throw new CustomUserMessageAuthenticationException('Invalid MyBB cookie format.');
        }

        [$uidString, $loginkey] = $parts;
        $uid = (int) $uidString;

        if ($uid <= 0 || $loginkey === '') {
            throw new CustomUserMessageAuthenticationException('Invalid MyBB cookie data.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $uid, function (string $identifier) use ($loginkey): ?object {
                $user = $this->userRepository->findOneBy([
                    'uid' => (int) $identifier,
                    'loginkey' => $loginkey,
                ]);

                if ($user === null) {
                    throw new CustomUserMessageAuthenticationException('MyBB session invalid.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
