<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'mybb_attachments')]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'aid', type: 'integer')]
    private ?int $aid = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'pid', referencedColumnName: 'pid', nullable: false)]
    private ?Post $post = null;

    #[ORM\Column(name: 'tid', type: 'integer')]
    private int $tid = 0;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'filename', type: 'string', length: 120)]
    private string $filename = '';

    #[ORM\Column(name: 'filetype', type: 'string', length: 120)]
    private string $filetype = '';

    #[ORM\Column(name: 'filesize', type: 'integer')]
    private int $filesize = 0;

    #[ORM\Column(name: 'attachname', type: 'string', length: 120)]
    private string $attachname = '';

    #[ORM\Column(name: 'downloads', type: 'integer')]
    private int $downloads = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    public function getAid(): ?int
    {
        return $this->aid;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): void
    {
        $this->post = $post;
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getFiletype(): string
    {
        return $this->filetype;
    }

    public function setFiletype(string $filetype): void
    {
        $this->filetype = $filetype;
    }

    public function getFilesize(): int
    {
        return $this->filesize;
    }

    public function setFilesize(int $filesize): void
    {
        $this->filesize = $filesize;
    }

    public function getAttachname(): string
    {
        return $this->attachname;
    }

    public function setAttachname(string $attachname): void
    {
        $this->attachname = $attachname;
    }

    public function getDownloads(): int
    {
        return $this->downloads;
    }

    public function setDownloads(int $downloads): void
    {
        $this->downloads = $downloads;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->attachname, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true);
    }
}
