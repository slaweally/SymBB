<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserGroup>
 */
final class UserGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGroup::class);
    }

    public function findByGid(int $gid): ?UserGroup
    {
        return $this->findOneBy(['gid' => $gid]);
    }
}
