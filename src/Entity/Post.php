<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'mybb_posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'pid', type: 'integer')]
    private ?int $pid = null;

    #[ORM\ManyToOne(targetEntity: Thread::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'tid', referencedColumnName: 'tid', nullable: false)]
    private ?Thread $thread = null;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    private ?User $user = null;

    #[ORM\Column(name: 'username', type: 'string', length: 120)]
    private string $username = '';

    #[ORM\Column(name: 'subject', type: 'string', length: 120)]
    private string $subject = '';

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message = '';

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'visible', type: 'integer')]
    private int $visible = 1;

    /** @var Collection<int, Attachment> */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'post', cascade: ['persist'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    /** @return Collection<int, Attachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getThread(): ?Thread
    {
        return $this->thread;
    }

    public function setThread(Thread $thread): void
    {
        $this->thread = $thread;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
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

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getVisible(): int
    {
        return $this->visible;
    }

    public function setVisible(int $visible): void
    {
        $this->visible = $visible;
    }
}
