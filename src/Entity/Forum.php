<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: 'mybb_forums')]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'fid', type: 'integer')]
    private ?int $fid = null;

    #[ORM\Column(name: 'name', type: 'string', length: 120)]
    private string $name = '';

    #[ORM\Column(name: 'description', type: 'text')]
    private string $description = '';

    #[ORM\Column(name: 'type', type: 'string', length: 1)]
    private string $type = 'f';

    #[ORM\Column(name: 'pid', type: 'integer')]
    private int $pid = 0;

    #[ORM\Column(name: 'disporder', type: 'integer')]
    private int $disporder = 0;

    #[ORM\Column(name: 'threads', type: 'integer')]
    private int $threads = 0;

    #[ORM\Column(name: 'posts', type: 'integer')]
    private int $posts = 0;

    #[ORM\Column(name: 'lastpost', type: 'integer')]
    private int $lastpost = 0;

    #[ORM\Column(name: 'lastposter', type: 'string', length: 120)]
    private string $lastposter = '';

    #[ORM\Column(name: 'lastposteruid', type: 'integer')]
    private int $lastposteruid = 0;

    #[ORM\Column(name: 'lastposttid', type: 'integer')]
    private int $lastposttid = 0;

    #[ORM\Column(name: 'lastpostsubject', type: 'string', length: 120)]
    private string $lastpostsubject = '';

    #[ORM\Column(name: 'rules', type: 'text', nullable: true, options: ['default' => null])]
    private ?string $rules = null;

    #[ORM\Column(name: 'rulestitle', type: 'string', length: 200, nullable: true, options: ['default' => null])]
    private ?string $rulestitle = null;

    #[ORM\Column(name: 'rulestype', type: 'integer', nullable: true, options: ['default' => null])]
    private ?int $rulestype = null;

    public function getFid(): ?int
    {
        return $this->fid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getDisporder(): int
    {
        return $this->disporder;
    }

    public function getThreads(): int
    {
        return $this->threads;
    }

    public function getPosts(): int
    {
        return $this->posts;
    }

    public function getLastpost(): int
    {
        return $this->lastpost;
    }

    public function getLastposter(): string
    {
        return $this->lastposter;
    }

    public function getLastposteruid(): int
    {
        return $this->lastposteruid;
    }

    public function getLastposttid(): int
    {
        return $this->lastposttid;
    }

    public function getLastpostsubject(): string
    {
        return $this->lastpostsubject;
    }

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function getRulestitle(): ?string
    {
        return $this->rulestitle;
    }

    public function getRulestype(): ?int
    {
        return $this->rulestype;
    }
}
