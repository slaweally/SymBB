<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * MyBB legacy password hasher: md5(md5($salt) . md5($password))
 */
final class MyBBPasswordHasher implements PasswordHasherInterface
{
    use CheckPasswordLengthTrait;

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new \InvalidArgumentException('Password too long.');
        }

        $salt = $this->generateSalt();

        return $this->computeHash($plainPassword, $salt);
    }

    /**
     * Hash password with given salt (for MyBB compatibility - salt stored in user table).
     */
    public function hashWithSalt(#[\SensitiveParameter] string $plainPassword, string $salt): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new \InvalidArgumentException('Password too long.');
        }

        return $this->computeHash($plainPassword, $salt);
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, ?string $salt = null): bool
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            return false;
        }

        if ($salt === null || $salt === '') {
            return false;
        }

        return hash_equals($hashedPassword, $this->computeHash($plainPassword, $salt));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }

    private function computeHash(string $plainPassword, string $salt): string
    {
        return md5(md5($salt) . md5($plainPassword));
    }

    private function generateSalt(): string
    {
        return substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 10);
    }
}
