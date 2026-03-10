<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReputationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReputationRepository::class)]
#[ORM\Table(name: 'mybb_reputation')]
class Reputation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'rid', type: 'integer')]
    private ?int $rid = null;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'adduid', type: 'integer')]
    private int $adduid = 0;

    #[ORM\Column(name: 'pid', type: 'integer')]
    private int $pid = 0;

    #[ORM\Column(name: 'reputation', type: 'integer')]
    private int $reputation = 0;

    #[ORM\Column(name: 'comments', type: 'text')]
    private string $comments = '';

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getRid(): ?int
    {
        return $this->rid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getAdduid(): int
    {
        return $this->adduid;
    }

    public function setAdduid(int $adduid): void
    {
        $this->adduid = $adduid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): void
    {
        $this->reputation = $reputation;
    }

    public function getComments(): string
    {
        return $this->comments;
    }

    public function setComments(string $comments): void
    {
        $this->comments = $comments;
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
