<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarningTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarningTypeRepository::class)]
#[ORM\Table(name: 'mybb_warningtypes')]
class WarningType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'tid', type: 'integer')]
    private ?int $tid = null;

    #[ORM\Column(name: 'title', type: 'string', length: 200)]
    private string $title = '';

    #[ORM\Column(name: 'points', type: 'integer')]
    private int $points = 1;

    #[ORM\Column(name: 'expirationtime', type: 'integer')]
    private int $expirationtime = 0;

    public function getTid(): ?int
    {
        return $this->tid;
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

    public function getExpirationtime(): int
    {
        return $this->expirationtime;
    }

    public function setExpirationtime(int $expirationtime): void
    {
        $this->expirationtime = $expirationtime;
    }
}
