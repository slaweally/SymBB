<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrivateMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrivateMessage>
 */
final class PrivateMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrivateMessage::class);
    }

    public function findByUserAndFolder(int $uid, int $folder, ?int $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->andWhere('p.folder = :folder')
            ->setParameter('uid', $uid)
            ->setParameter('folder', $folder)
            ->orderBy('p.dateline', 'DESC');

        if ($status !== null) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findSentByUser(int $uid): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->andWhere('p.folder = 2')
            ->setParameter('uid', $uid)
            ->orderBy('p.dateline', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByUserAndFolder(int $uid, int $folder, ?int $status = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.pmid)')
            ->where('p.uid = :uid')
            ->andWhere('p.folder = :folder')
            ->setParameter('uid', $uid)
            ->setParameter('folder', $folder);

        if ($status !== null) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function deleteByUserAndFolders(int $uid, array $folderIds, bool $keepUnread = false): int
    {
        if (empty($folderIds)) {
            return 0;
        }

        $qb = $this->createQueryBuilder('p')
            ->delete()
            ->where('p.uid = :uid')
            ->andWhere('p.folder IN (:folders)')
            ->setParameter('uid', $uid)
            ->setParameter('folders', $folderIds);

        if ($keepUnread) {
            $qb->andWhere('p.status != 0');
        }

        return $qb->getQuery()->execute();
    }
}
