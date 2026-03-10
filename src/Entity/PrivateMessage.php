<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\PrivateMessageRepository::class)]
#[ORM\Table(name: 'mybb_privatemessages')]
class PrivateMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'pmid', type: 'integer')]
    private ?int $pmid = null;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'toid', type: 'integer')]
    private int $toid = 0;

    #[ORM\Column(name: 'fromid', type: 'integer')]
    private int $fromid = 0;

    #[ORM\Column(name: 'folder', type: 'integer')]
    private int $folder = 1;

    #[ORM\Column(name: 'subject', type: 'string', length: 120)]
    private string $subject = '';

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message = '';

    #[ORM\Column(name: 'status', type: 'integer')]
    private int $status = 0;

    #[ORM\Column(name: 'statustime', type: 'integer', nullable: true)]
    private ?int $statustime = null;

    #[ORM\Column(name: 'readtime', type: 'integer', nullable: true)]
    private ?int $readtime = null;

    #[ORM\Column(name: 'receipt', type: 'integer', nullable: true)]
    private ?int $receipt = null;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'deletetime', type: 'integer', nullable: true)]
    private ?int $deletetime = null;

    // Sanal özellikler (Eager loading icin)
    private ?User $fromUser = null;
    private ?User $toUser = null;

    public function getPmid(): ?int
    {
        return $this->pmid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getToid(): int
    {
        return $this->toid;
    }

    public function setToid(int $toid): void
    {
        $this->toid = $toid;
    }

    public function getFromid(): int
    {
        return $this->fromid;
    }

    public function setFromid(int $fromid): void
    {
        $this->fromid = $fromid;
    }

    public function getFolder(): int
    {
        return $this->folder;
    }

    public function setFolder(int $folder): void
    {
        $this->folder = $folder;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getStatustime(): ?int
    {
        return $this->statustime;
    }

    public function setStatustime(?int $statustime): void
    {
        $this->statustime = $statustime;
    }

    public function getReadtime(): ?int
    {
        return $this->readtime;
    }

    public function setReadtime(?int $readtime): void
    {
        $this->readtime = $readtime;
    }

    public function getReceipt(): ?int
    {
        return $this->receipt;
    }

    public function setReceipt(?int $receipt): void
    {
        $this->receipt = $receipt;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getDeletetime(): ?int
    {
        return $this->deletetime;
    }

    public function setDeletetime(?int $deletetime): void
    {
        $this->deletetime = $deletetime;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $fromUser): void
    {
        $this->fromUser = $fromUser;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $toUser): void
    {
        $this->toUser = $toUser;
    }
}
