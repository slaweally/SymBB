<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ThreadRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThreadRating>
 */
class ThreadRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThreadRating::class);
    }

    public function hasUserRated(int $tid, int $uid): bool
    {
        return $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.tid = :tid')
            ->andWhere('r.uid = :uid')
            ->setParameter('tid', $tid)
            ->setParameter('uid', $uid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
