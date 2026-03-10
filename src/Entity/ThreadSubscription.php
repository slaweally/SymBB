<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThreadSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreadSubscriptionRepository::class)]
#[ORM\Table(name: 'mybb_threadsubscriptions')]
class ThreadSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'sid', type: 'integer')]
    private ?int $sid = null;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'tid', type: 'integer')]
    private int $tid = 0;

    #[ORM\Column(name: 'notification', type: 'integer')]
    private int $notification = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getSid(): ?int
    {
        return $this->sid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getTid(): int
    {
        return $this->tid;
    }

    public function setTid(int $tid): void
    {
        $this->tid = $tid;
    }

    public function getNotification(): int
    {
        return $this->notification;
    }

    public function setNotification(int $notification): void
    {
        $this->notification = $notification;
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
