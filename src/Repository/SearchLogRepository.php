<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SearchLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchLog>
 */
final class SearchLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchLog::class);
    }

    public function findBySid(string $sid): ?SearchLog
    {
        return $this->findOneBy(['sid' => $sid]);
    }
}
