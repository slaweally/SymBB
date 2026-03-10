<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarningRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarningRepository::class)]
#[ORM\Table(name: 'mybb_warnings')]
class Warning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'wid', type: 'integer')]
    private ?int $wid = null;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'issuedby', type: 'integer')]
    private int $issuedby = 0;

    #[ORM\Column(name: 'tid', type: 'integer')]
    private int $tid = 0;

    #[ORM\Column(name: 'pid', type: 'integer')]
    private int $pid = 0;

    #[ORM\Column(name: 'title', type: 'string', length: 200)]
    private string $title = '';

    #[ORM\Column(name: 'points', type: 'integer')]
    private int $points = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'notes', type: 'text')]
    private string $notes = '';

    public function getWid(): ?int
    {
        return $this->wid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getIssuedby(): int
    {
        return $this->issuedby;
    }

    public function setIssuedby(int $issuedby): void
    {
        $this->issuedby = $issuedby;
    }

    public function getTid(): int
    {
        return $this->tid;
    }

    public function setTid(int $tid): void
    {
        $this->tid = $tid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }
}
