<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PollVoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollVoteRepository::class)]
#[ORM\Table(name: 'mybb_pollvotes')]
class PollVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'vid', type: 'integer')]
    private ?int $vid = null;

    #[ORM\Column(name: 'pid', type: 'integer')]
    private int $pid = 0;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'voteoption', type: 'integer')]
    private int $voteoption = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getVoteoption(): int
    {
        return $this->voteoption;
    }

    public function setVoteoption(int $voteoption): void
    {
        $this->voteoption = $voteoption;
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
