<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Thread;
use App\Entity\ThreadSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thread>
 */
final class ThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thread::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.tid)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findWithPosts(int $tid): ?Thread
    {
        return $this->createQueryBuilder('t')
            ->addSelect('p')
            ->leftJoin('t.posts', 'p')
            ->where('t.tid = :tid')
            ->setParameter('tid', $tid)
            ->orderBy('p.dateline', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Thread[] */
    public function findByForum(int $fid): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.fid = :fid')
            ->setParameter('fid', $fid)
            ->orderBy('t.dateline', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Duyuru forumundan en son konuları mesaj içeriğiyle birlikte çeker.
     *
     * @return Thread[]
     */
    public function findAnnouncements(int $fid, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('p')
            ->leftJoin('t.posts', 'p')
            ->where('t.fid = :fid')
            ->setParameter('fid', $fid)
            ->andWhere('t.visible = 1')
            ->orderBy('t.dateline', 'DESC')
            ->addOrderBy('p.dateline', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Thread[] */
    public function findLatestForRss(?int $fid, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.visible = 1')
            ->andWhere("t.closed = '' OR t.closed NOT LIKE 'moved|%'")
            ->orderBy('t.dateline', 'DESC')
            ->setMaxResults($limit);

        if ($fid !== null && $fid > 0) {
            $qb->andWhere('t.fid = :fid')->setParameter('fid', $fid);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Kullanıcının abone olduğu konuları çeker (subscription listesi için).
     *
     * @return Thread[]
     */
    public function findSubscribedByUser(int $uid): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin(ThreadSubscription::class, 's', 'WITH', 's.tid = t.tid AND s.uid = :uid')
            ->setParameter('uid', $uid)
            ->where('t.visible = 1')
            ->orderBy('t.dateline', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Kullanıcının son aktif olduğu tarihten sonraki konuları çeker (yeni mesajlar).
     *
     * @return Thread[]
     */
    public function findNewSince(int $sinceTimestamp): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.visible = 1')
            ->andWhere('t.dateline > :since')
            ->setParameter('since', $sinceTimestamp)
            ->orderBy('t.dateline', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
