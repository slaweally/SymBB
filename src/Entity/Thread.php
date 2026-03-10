<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
#[ORM\Table(name: 'mybb_threads')]
class Thread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'tid', type: 'integer')]
    private ?int $tid = null;

    #[ORM\Column(name: 'fid', type: 'integer')]
    private int $fid = 0;

    #[ORM\Column(name: 'subject', type: 'string', length: 120)]
    private string $subject = '';

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'username', type: 'string', length: 120)]
    private string $username = '';

    #[ORM\Column(name: 'views', type: 'integer')]
    private int $views = 0;

    #[ORM\Column(name: 'replies', type: 'integer')]
    private int $replies = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'lastpost', type: 'integer')]
    private int $lastpost = 0;

    #[ORM\Column(name: 'notes', type: 'text')]
    private string $notes = '';

    #[ORM\Column(name: 'visible', type: 'integer')]
    private int $visible = 1;

    #[ORM\Column(name: 'closed', type: 'string', length: 30)]
    private string $closed = '';

    #[ORM\Column(name: 'sticky', type: 'integer')]
    private int $stickied = 0;

    #[ORM\Column(name: 'poll', type: 'integer')]
    private int $poll = 0;

    #[ORM\Column(name: 'numratings', type: 'integer', options: ['default' => 0])]
    private int $numratings = 0;

    #[ORM\Column(name: 'totalratings', type: 'integer', options: ['default' => 0])]
    private int $totalratings = 0;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'thread')]
    #[ORM\OrderBy(['dateline' => 'ASC'])]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function getFid(): int
    {
        return $this->fid;
    }

    public function setFid(int $fid): void
    {
        $this->fid = $fid;
    }

    public function getTid(): ?int
    {
        return $this->tid;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function getReplies(): int
    {
        return $this->replies;
    }

    public function setReplies(int $replies): void
    {
        $this->replies = $replies;
    }

    public function incrementReplies(): void
    {
        $this->replies++;
    }

    public function decrementReplies(): void
    {
        if ($this->replies > 0) {
            $this->replies--;
        }
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getLastpost(): int
    {
        return $this->lastpost;
    }

    public function setLastpost(int $lastpost): void
    {
        $this->lastpost = $lastpost;
    }

    public function getVisible(): int
    {
        return $this->visible;
    }

    public function setVisible(int $visible): void
    {
        $this->visible = $visible;
    }

    public function getClosed(): string
    {
        return $this->closed;
    }

    public function setClosed(string $closed): void
    {
        $this->closed = $closed;
    }

    public function getStickied(): int
    {
        return $this->stickied;
    }

    public function setStickied(int $stickied): void
    {
        $this->stickied = $stickied;
    }

    public function getPoll(): int
    {
        return $this->poll;
    }

    public function setPoll(int $poll): void
    {
        $this->poll = $poll;
    }

    public function getNumratings(): int
    {
        return $this->numratings;
    }

    public function setNumratings(int $numratings): void
    {
        $this->numratings = $numratings;
    }

    public function getTotalratings(): int
    {
        return $this->totalratings;
    }

    public function setTotalratings(int $totalratings): void
    {
        $this->totalratings = $totalratings;
    }

    public function incrementNumratings(): void
    {
        $this->numratings++;
    }

    public function addToTotalratings(int $rating): void
    {
        $this->totalratings += $rating;
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }
}
