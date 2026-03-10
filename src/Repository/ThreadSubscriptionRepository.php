<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ThreadSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThreadSubscription>
 */
final class ThreadSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThreadSubscription::class);
    }

    public function findByUserAndThread(int $uid, int $tid): ?ThreadSubscription
    {
        return $this->findOneBy(['uid' => $uid, 'tid' => $tid]);
    }
}
