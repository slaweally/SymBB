<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.uid)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function findActiveUsers(int $limitTime): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastactive IS NOT NULL')
            ->andWhere('u.lastactive > :limitTime')
            ->andWhere('u.uid > 0')
            ->setParameter('limitTime', $limitTime)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLastRegistered(): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.uid > 0')
            ->orderBy('u.regdate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUsernameLike(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :username')
            ->setParameter('username', '%' . $username . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Admin (4), Süper Mod (3), Mod (6) - usergroup veya displaygroup
     *
     * @return User[]
     */
    public function findTeamMembers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.uid > 0')
            ->andWhere('u.usergroup IN (:gids) OR u.displaygroup IN (:gids)')
            ->setParameter('gids', [4, 3, 6])
            ->orderBy('u.usergroup', 'ASC')
            ->addOrderBy('u.displaygroup', 'ASC')
            ->addOrderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
