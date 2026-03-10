<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\Table(name: 'mybb_usergroups')]
class UserGroup
{
    #[ORM\Id]
    #[ORM\Column(name: 'gid', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $gid;

    #[ORM\Column(name: 'title', type: 'string', length: 200)]
    private string $title = '';

    #[ORM\Column(name: 'namestyle', type: 'string', length: 300)]
    private string $namestyle = '';

    public function getGid(): int
    {
        return $this->gid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getNamestyle(): string
    {
        return $this->namestyle;
    }
}
