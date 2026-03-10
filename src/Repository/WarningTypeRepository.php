<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WarningType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarningType>
 */
final class WarningTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarningType::class);
    }

    /** @return WarningType[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
