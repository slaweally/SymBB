<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThreadRatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreadRatingRepository::class)]
#[ORM\Table(name: 'mybb_threadratings')]
class ThreadRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'rid', type: 'integer')]
    private ?int $rid = null;

    #[ORM\Column(name: 'tid', type: 'integer')]
    private int $tid = 0;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'rating', type: 'integer')]
    private int $rating = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getRid(): ?int
    {
        return $this->rid;
    }

    public function getTid(): int
    {
        return $this->tid;
    }

    public function setTid(int $tid): void
    {
        $this->tid = $tid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }
}
