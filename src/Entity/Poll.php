<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PollRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollRepository::class)]
#[ORM\Table(name: 'mybb_polls')]
class Poll
{
    public const OPTIONS_SEPARATOR = '||~|~||';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'pid', type: 'integer')]
    private ?int $pid = null;

    #[ORM\Column(name: 'tid', type: 'integer')]
    private int $tid = 0;

    #[ORM\Column(name: 'question', type: 'string', length: 255)]
    private string $question = '';

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'options', type: 'text')]
    private string $options = '';

    #[ORM\Column(name: 'votes', type: 'text')]
    private string $votes = '';

    #[ORM\Column(name: 'numvotes', type: 'integer')]
    private int $numvotes = 0;

    #[ORM\Column(name: 'timeout', type: 'integer')]
    private int $timeout = 0;

    #[ORM\Column(name: 'closed', type: 'integer')]
    private int $closed = 0;

    #[ORM\Column(name: 'multiple', type: 'integer')]
    private int $multiple = 0;

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getTid(): int
    {
        return $this->tid;
    }

    public function setTid(int $tid): void
    {
        $this->tid = $tid;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getOptions(): string
    {
        return $this->options;
    }

    public function setOptions(string $options): void
    {
        $this->options = $options;
    }

    /** @return string[] */
    public function getOptionsArray(): array
    {
        return $this->options !== '' ? explode(self::OPTIONS_SEPARATOR, $this->options) : [];
    }

    public function getVotes(): string
    {
        return $this->votes;
    }

    public function setVotes(string $votes): void
    {
        $this->votes = $votes;
    }

    /** @return int[] */
    public function getVotesArray(): array
    {
        if ($this->votes === '') {
            return [];
        }
        $arr = explode(self::OPTIONS_SEPARATOR, $this->votes);
        return array_map('intval', $arr);
    }

    public function getNumvotes(): int
    {
        return $this->numvotes;
    }

    public function setNumvotes(int $numvotes): void
    {
        $this->numvotes = $numvotes;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getClosed(): int
    {
        return $this->closed;
    }

    public function setClosed(int $closed): void
    {
        $this->closed = $closed;
    }

    public function getMultiple(): int
    {
        return $this->multiple;
    }

    public function setMultiple(int $multiple): void
    {
        $this->multiple = $multiple;
    }

    public function isExpired(): bool
    {
        if ($this->timeout <= 0) {
            return false;
        }
        return time() > ($this->dateline + $this->timeout * 86400);
    }
}
