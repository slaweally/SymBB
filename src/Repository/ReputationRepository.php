<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reputation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reputation>
 */
final class ReputationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reputation::class);
    }

    public function hasUserGivenRepToPost(int $adduid, int $pid): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.rid)')
            ->where('r.adduid = :adduid')
            ->andWhere('r.pid = :pid')
            ->setParameter('adduid', $adduid)
            ->setParameter('pid', $pid)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return Reputation[] */
    public function findByUser(int $uid, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.uid = :uid')
            ->setParameter('uid', $uid)
            ->orderBy('r.dateline', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(int $uid): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.rid)')
            ->where('r.uid = :uid')
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
