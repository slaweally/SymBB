<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
final class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /** @return Forum[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.disporder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Kategoriler (type=c) ve altındaki forumlar (type=f, pid=eşleşen). disporder sırası korunur.
     *
     * @return array{categories: array<array{fid: int|null, name: string, forums: Forum[]}>}
     */
    public function findCategoriesWithForums(): array
    {
        $all = $this->findAllOrdered();
        $categories = [];
        $forumsByPid = [];

        foreach ($all as $item) {
            if ($item->getType() === 'c') {
                $categories[] = [
                    'fid' => $item->getFid(),
                    'name' => $item->getName(),
                    'forums' => [],
                ];
            } else {
                $pid = $item->getPid();
                $forumsByPid[$pid] = $forumsByPid[$pid] ?? [];
                $forumsByPid[$pid][] = $item;
            }
        }

        foreach ($categories as &$cat) {
            $fid = $cat['fid'] ?? 0;
            $cat['forums'] = $forumsByPid[$fid] ?? [];
        }

        return ['categories' => $categories];
    }
}
