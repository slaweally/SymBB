<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
final class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /**
     * @return Attachment[]
     */
    public function findByPost(int $pid): array
    {
        $post = $this->getEntityManager()->getReference(Post::class, $pid);
        return $this->findBy(['post' => $post], ['aid' => 'ASC']);
    }
}
