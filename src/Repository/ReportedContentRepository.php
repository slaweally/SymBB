<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<\App\Entity\ReportedContent>
 */
final class ReportedContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, \App\Entity\ReportedContent::class);
    }

    public function countPendingPostReports(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.rid)')
            ->where("r.type = 'post'")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasUserReportedPost(int $uid, int $pid): bool
    {
        return $this->createQueryBuilder('r')
            ->select('1')
            ->where("r.type = 'post'")
            ->andWhere('r.cid = :pid')
            ->andWhere('r.uid = :uid')
            ->setParameter('pid', $pid)
            ->setParameter('uid', $uid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
