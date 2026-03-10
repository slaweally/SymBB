<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReportedContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportedContentRepository::class)]
#[ORM\Table(name: 'mybb_reportedcontent')]
class ReportedContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'rid', type: 'integer')]
    private ?int $rid = null;

    #[ORM\Column(name: 'cid', type: 'integer')]
    private int $cid = 0;

    #[ORM\Column(name: 'type', type: 'string', length: 50)]
    private string $type = 'post';

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'reason', type: 'text')]
    private string $reason = '';

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getRid(): ?int
    {
        return $this->rid;
    }

    public function getCid(): int
    {
        return $this->cid;
    }

    public function setCid(int $cid): void
    {
        $this->cid = $cid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
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
