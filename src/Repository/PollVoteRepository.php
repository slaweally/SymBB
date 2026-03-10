<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PollVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PollVote>
 */
final class PollVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PollVote::class);
    }

    public function hasUserVoted(int $pid, int $uid): bool
    {
        return $this->createQueryBuilder('v')
            ->select('1')
            ->where('v.pid = :pid')
            ->andWhere('v.uid = :uid')
            ->setParameter('pid', $pid)
            ->setParameter('uid', $uid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /** @return PollVote[] */
    public function findUserVotes(int $pid, int $uid): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.pid = :pid')
            ->andWhere('v.uid = :uid')
            ->setParameter('pid', $pid)
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getResult();
    }
}
