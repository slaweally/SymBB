<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SearchLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchLogRepository::class)]
#[ORM\Table(name: 'mybb_searchlog')]
class SearchLog
{
    #[ORM\Id]
    #[ORM\Column(name: 'sid', type: 'string', length: 32)]
    private string $sid;

    #[ORM\Column(name: 'uid', type: 'integer')]
    private int $uid = 0;

    #[ORM\Column(name: 'dateline', type: 'integer')]
    private int $dateline = 0;

    #[ORM\Column(name: 'ipaddress', type: 'string', length: 255, nullable: true)]
    private ?string $ipaddress = null;

    #[ORM\Column(name: 'threads', type: 'text', nullable: true)]
    private ?string $threads = null;

    #[ORM\Column(name: 'posts', type: 'text', nullable: true)]
    private ?string $posts = null;

    #[ORM\Column(name: 'resulttype', type: 'string', length: 20)]
    private string $resulttype = 'posts';

    #[ORM\Column(name: 'querycache', type: 'text', nullable: true)]
    private ?string $querycache = null;

    #[ORM\Column(name: 'keywords', type: 'string', length: 255, nullable: true)]
    private ?string $keywords = null;

    public function getSid(): string
    {
        return $this->sid;
    }

    public function setSid(string $sid): void
    {
        $this->sid = $sid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getDateline(): int
    {
        return $this->dateline;
    }

    public function setDateline(int $dateline): void
    {
        $this->dateline = $dateline;
    }

    public function getIpaddress(): ?string
    {
        return $this->ipaddress;
    }

    public function setIpaddress(?string $ipaddress): void
    {
        $this->ipaddress = $ipaddress;
    }

    public function getThreads(): ?string
    {
        return $this->threads;
    }

    public function setThreads(?string $threads): void
    {
        $this->threads = $threads;
    }

    public function getPosts(): ?string
    {
        return $this->posts;
    }

    public function setPosts(?string $posts): void
    {
        $this->posts = $posts;
    }

    public function getResulttype(): string
    {
        return $this->resulttype;
    }

    public function setResulttype(string $resulttype): void
    {
        $this->resulttype = $resulttype;
    }

    public function getQuerycache(): ?string
    {
        return $this->querycache;
    }

    public function setQuerycache(?string $querycache): void
    {
        $this->querycache = $querycache;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /** @return int[] */
    public function getPostIds(): array
    {
        $s = $this->posts ?? '';
        if ($s === '') {
            return [];
        }
        $arr = explode(',', $s);
        return array_map('intval', array_filter($arr));
    }

    /** @return int[] */
    public function getThreadIds(): array
    {
        $s = $this->threads ?? '';
        if ($s === '') {
            return [];
        }
        $arr = explode(',', $s);
        return array_map('intval', array_filter($arr));
    }
}
