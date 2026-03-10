<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
final class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.pid)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Forumdaki en son mesajları "Son Tartışmalar" bloğu için çeker.
     *
     * @return Post[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->join('p.thread', 't')
            ->where('t.visible = 1')
            ->orderBy('p.dateline', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Konuya kimler yazdı - kullanıcı bazında mesaj sayıları.
     *
     * @return array<array{uid: int|null, username: string, posts: int}>
     */
    public function findWhoPostedInThread(int $tid, bool $sortByUsername = false): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sort = $sortByUsername ? 'p.username ASC' : 'posts DESC';
        $sql = "
            SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username
            FROM mybb_posts p
            LEFT JOIN mybb_users u ON (u.uid = p.uid)
            WHERE p.tid = :tid AND p.visible = 1
            GROUP BY u.uid, p.username, u.username
            ORDER BY {$sort}
        ";
        $stmt = $conn->executeQuery($sql, ['tid' => $tid]);

        $result = [];
        while ($row = $stmt->fetchAssociative()) {
            $username = $row['username'] ?: $row['postusername'] ?: 'Misafir';
            $result[] = [
                'uid' => $row['uid'] ? (int) $row['uid'] : null,
                'username' => $username,
                'posts' => (int) $row['posts'],
            ];
        }

        return $result;
    }

    /**
     * @return Post[]
     */
    public function searchPosts(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->join('p.thread', 't')
            ->where('p.message LIKE :keyword OR p.subject LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('p.dateline', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Gelişmiş arama: anahtar kelime, yazar, forum, son X gün filtreleri.
     *
     * @param array{keywords?: string, author_uid?: int|null, forums?: int[], days?: int, match_type?: string} $criteria
     * @return Post[]
     */
    public function advancedSearch(array $criteria): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('t')
            ->join('p.thread', 't')
            ->where('t.visible = 1');

        $keywords = trim($criteria['keywords'] ?? '');
        $authorUid = $criteria['author_uid'] ?? null;
        $forums = $criteria['forums'] ?? [];
        $forums = is_array($forums) ? array_filter(array_map('intval', $forums)) : [];
        $days = (int) ($criteria['days'] ?? 0);
        $matchType = $criteria['match_type'] ?? 'and';

        $paramIndex = 0;

        if ($keywords !== '') {
            $words = preg_split('/\s+/', $keywords, -1, PREG_SPLIT_NO_EMPTY);
            if ($matchType === 'or' && count($words) > 1) {
                $orX = $qb->expr()->orX();
                foreach ($words as $i => $w) {
                    $param = 'kw' . $paramIndex++;
                    $orX->add($qb->expr()->orX(
                        $qb->expr()->like('p.message', ':' . $param),
                        $qb->expr()->like('p.subject', ':' . $param)
                    ));
                    $qb->setParameter($param, '%' . $w . '%');
                }
                $qb->andWhere($orX);
            } else {
                foreach ($words as $w) {
                    $param = 'kw' . $paramIndex++;
                    $qb->andWhere(
                        $qb->expr()->orX(
                            $qb->expr()->like('p.message', ':' . $param),
                            $qb->expr()->like('p.subject', ':' . $param)
                        )
                    );
                    $qb->setParameter($param, '%' . $w . '%');
                }
            }
        }

        if ($authorUid !== null && $authorUid > 0) {
            $qb->andWhere('p.uid = :authorUid');
            $qb->setParameter('authorUid', $authorUid);
        }

        if (count($forums) > 0) {
            $qb->andWhere('t.fid IN (:fids)');
            $qb->setParameter('fids', $forums);
        }

        if ($days > 0) {
            $cutoff = time() - ($days * 24 * 60 * 60);
            $qb->andWhere('p.dateline >= :cutoff');
            $qb->setParameter('cutoff', $cutoff);
        }

        return $qb
            ->orderBy('p.dateline', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Belirtilen pid listesine göre postları sırayla döndürür (arama SID cache için).
     *
     * @param int[] $pids
     * @return Post[]
     */
    public function findByIdsOrdered(array $pids): array
    {
        if (count($pids) === 0) {
            return [];
        }
        $pids = array_values(array_unique(array_filter(array_map('intval', $pids))));
        if (count($pids) === 0) {
            return [];
        }
        $qb = $this->createQueryBuilder('p')
            ->addSelect('t')
            ->join('p.thread', 't')
            ->where('p.pid IN (:pids)')
            ->setParameter('pids', $pids);
        $results = $qb->getQuery()->getResult();
        $byPid = [];
        foreach ($results as $p) {
            $byPid[$p->getPid()] = $p;
        }
        $ordered = [];
        foreach ($pids as $pid) {
            if (isset($byPid[$pid])) {
                $ordered[] = $byPid[$pid];
            }
        }
        return $ordered;
    }

    /**
     * Kullanıcının taslaklarını (visible=2) döndürür.
     * Hem konu taslakları hem yanıt taslakları post tablosunda tutulur.
     *
     * @return Post[]
     */
    public function findDraftsByUser(int $uid): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->join('p.thread', 't')
            ->where('p.uid = :uid')
            ->andWhere('p.visible = 2')
            ->setParameter('uid', $uid)
            ->orderBy('p.dateline', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
