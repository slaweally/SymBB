<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'mybb_users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'uid', type: 'integer')]
    private ?int $uid = null;

    #[ORM\Column(name: 'username', type: 'string', length: 120)]
    private string $username = '';

    #[ORM\Column(name: 'password', type: 'string', length: 120)]
    private string $password = '';

    #[ORM\Column(name: 'salt', type: 'string', length: 10)]
    private string $salt = '';

    #[ORM\Column(name: 'loginkey', type: 'string', length: 50)]
    private string $loginkey = '';

    #[ORM\Column(name: 'email', type: 'string', length: 220)]
    private string $email = '';

    #[ORM\Column(name: 'usergroup', type: 'integer')]
    private int $usergroup = 0;

    #[ORM\Column(name: 'displaygroup', type: 'integer')]
    private int $displaygroup = 0;

    #[ORM\Column(name: 'regdate', type: 'integer')]
    private int $regdate = 0;

    #[ORM\Column(name: 'avatar', type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(name: 'usertitle', type: 'string', length: 250, nullable: true)]
    private ?string $usertitle = null;

    #[ORM\Column(name: 'postnum', type: 'integer')]
    private int $postnum = 0;

    #[ORM\Column(name: 'threadnum', type: 'integer')]
    private int $threadnum = 0;

    #[ORM\Column(name: 'signature', type: 'text')]
    private string $signature = '';

    #[ORM\Column(name: 'allownotices', type: 'integer')]
    private int $allownotices = 1;

    #[ORM\Column(name: 'hideemail', type: 'integer')]
    private int $hideemail = 0;

    #[ORM\Column(name: 'receivepms', type: 'integer')]
    private int $receivepms = 1;

    #[ORM\Column(name: 'pmnotice', type: 'integer')]
    private int $pmnotice = 1;

    #[ORM\Column(name: 'showsigs', type: 'integer')]
    private int $showsigs = 1;

    #[ORM\Column(name: 'showavatars', type: 'integer')]
    private int $showavatars = 1;

    #[ORM\Column(name: 'showquickreply', type: 'integer')]
    private int $showquickreply = 1;

    #[ORM\Column(name: 'ppp', type: 'integer')]
    private int $ppp = 0;

    #[ORM\Column(name: 'tpp', type: 'integer')]
    private int $tpp = 0;

    #[ORM\Column(name: 'timezone', type: 'string', length: 5)]
    private string $timezone = '0';

    #[ORM\Column(name: 'dst', type: 'integer')]
    private int $dst = 0;

    #[ORM\Column(name: 'lastactive', type: 'integer', nullable: true)]
    private ?int $lastactive = null;

    #[ORM\Column(name: 'lastvisit', type: 'integer', nullable: true)]
    private ?int $lastvisit = null;

    #[ORM\Column(name: 'reputation', type: 'integer')]
    private int $reputation = 0;

    #[ORM\Column(name: 'warningpoints', type: 'integer', options: ['default' => 0])]
    private int $warningpoints = 0;

    #[ORM\Column(name: 'lostpwcode', type: 'string', length: 50, nullable: true)]
    private ?string $lostpwcode = null;

    #[ORM\Column(name: 'pmfolders', type: 'text', nullable: true)]
    private ?string $pmfolders = null;

    #[ORM\Column(name: 'skype', type: 'string', length: 80, nullable: true)]
    private ?string $skype = null;

    #[ORM\Column(name: 'google', type: 'string', length: 80, nullable: true)]
    private ?string $google = null;

    #[ORM\Column(name: 'icq', type: 'string', length: 20, nullable: true)]
    private ?string $icq = null;

    #[ORM\Column(name: 'invisible', type: 'integer', options: ['default' => 0])]
    private int $invisible = 0;

    #[ORM\Column(name: 'location', type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'buddylist', type: 'text', nullable: true)]
    private ?string $buddylist = null;

    #[ORM\Column(name: 'ignorelist', type: 'text', nullable: true)]
    private ?string $ignorelist = null;

    #[ORM\Column(name: 'notepad', type: 'text', nullable: true)]
    private ?string $notepad = null;

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function setSalt(string $salt): void
    {
        $this->salt = $salt;
    }

    public function getLoginkey(): string
    {
        return $this->loginkey;
    }

    public function setLoginkey(string $loginkey): void
    {
        $this->loginkey = $loginkey;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUsergroup(): int
    {
        return $this->usergroup;
    }

    public function setUsergroup(int $usergroup): void
    {
        $this->usergroup = $usergroup;
    }

    public function getDisplaygroup(): int
    {
        return $this->displaygroup;
    }

    public function setDisplaygroup(int $displaygroup): void
    {
        $this->displaygroup = $displaygroup;
    }

    public function getRegdate(): int
    {
        return $this->regdate;
    }

    public function setRegdate(int $regdate): void
    {
        $this->regdate = $regdate;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * Returns the avatar path for display. If avatar is empty or null,
     * returns default_avatar.png path.
     */
    public function getAvatarPath(): string
    {
        return $this->getAvatarUrl();
    }

    /**
     * Returns the avatar URL for display. If avatar is empty, or the file
     * does not exist in public/uploads/avatars/, returns default_avatar.png.
     * Path is relative to public/ for use with asset().
     */
    public function getAvatarUrl(): string
    {
        $avatar = $this->avatar;
        if ($avatar === null || $avatar === '') {
            return 'themes/default/images/default_avatar.png';
        }
        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }
        $projectDir = dirname(__DIR__, 2);
        $basename = basename($avatar);
        $avatarPath = $projectDir . '/public/uploads/avatars/' . $basename;
        if (!is_file($avatarPath)) {
            return 'themes/default/images/default_avatar.png';
        }
        return 'uploads/avatars/' . $basename;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getUsertitle(): ?string
    {
        return $this->usertitle;
    }

    public function setUsertitle(?string $usertitle): void
    {
        $this->usertitle = $usertitle;
    }

    public function getPostnum(): int
    {
        return $this->postnum;
    }

    public function setPostnum(int $postnum): void
    {
        $this->postnum = $postnum;
    }

    public function getThreadnum(): int
    {
        return $this->threadnum;
    }

    public function setThreadnum(int $threadnum): void
    {
        $this->threadnum = $threadnum;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }

    public function getAllownotices(): int
    {
        return $this->allownotices;
    }

    public function setAllownotices(int $allownotices): void
    {
        $this->allownotices = $allownotices;
    }

    public function getHideemail(): int
    {
        return $this->hideemail;
    }

    public function setHideemail(int $hideemail): void
    {
        $this->hideemail = $hideemail;
    }

    public function getReceivepms(): int
    {
        return $this->receivepms;
    }

    public function setReceivepms(int $receivepms): void
    {
        $this->receivepms = $receivepms;
    }

    public function getPmnotice(): int
    {
        return $this->pmnotice;
    }

    public function setPmnotice(int $pmnotice): void
    {
        $this->pmnotice = $pmnotice;
    }

    public function getShowsigs(): int
    {
        return $this->showsigs;
    }

    public function setShowsigs(int $showsigs): void
    {
        $this->showsigs = $showsigs;
    }

    public function getShowavatars(): int
    {
        return $this->showavatars;
    }

    public function setShowavatars(int $showavatars): void
    {
        $this->showavatars = $showavatars;
    }

    public function getShowquickreply(): int
    {
        return $this->showquickreply;
    }

    public function setShowquickreply(int $showquickreply): void
    {
        $this->showquickreply = $showquickreply;
    }

    public function getPpp(): int
    {
        return $this->ppp;
    }

    public function setPpp(int $ppp): void
    {
        $this->ppp = $ppp;
    }

    public function getTpp(): int
    {
        return $this->tpp;
    }

    public function setTpp(int $tpp): void
    {
        $this->tpp = $tpp;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getDst(): int
    {
        return $this->dst;
    }

    public function setDst(int $dst): void
    {
        $this->dst = $dst;
    }

    public function getLastactive(): ?int
    {
        return $this->lastactive;
    }

    public function setLastactive(?int $lastactive): void
    {
        $this->lastactive = $lastactive;
    }

    public function getLastvisit(): ?int
    {
        return $this->lastvisit;
    }

    public function setLastvisit(?int $lastvisit): void
    {
        $this->lastvisit = $lastvisit;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): void
    {
        $this->reputation = $reputation;
    }

    public function getWarningpoints(): int
    {
        return $this->warningpoints;
    }

    public function setWarningpoints(int $warningpoints): void
    {
        $this->warningpoints = $warningpoints;
    }

    public function getLostpwcode(): ?string
    {
        return $this->lostpwcode;
    }

    public function setLostpwcode(?string $lostpwcode): void
    {
        $this->lostpwcode = $lostpwcode;
    }

    public function getPmfolders(): ?string
    {
        return $this->pmfolders;
    }

    public function setPmfolders(?string $pmfolders): void
    {
        $this->pmfolders = $pmfolders;
    }

    public function getSkype(): ?string
    {
        return $this->skype;
    }

    public function setSkype(?string $skype): void
    {
        $this->skype = $skype;
    }

    public function getGoogle(): ?string
    {
        return $this->google;
    }

    public function setGoogle(?string $google): void
    {
        $this->google = $google;
    }

    public function getIcq(): ?string
    {
        return $this->icq;
    }

    public function setIcq(?string $icq): void
    {
        $this->icq = $icq;
    }

    public function getInvisible(): int
    {
        return $this->invisible;
    }

    public function setInvisible(int $invisible): void
    {
        $this->invisible = $invisible;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getBuddylist(): ?string
    {
        return $this->buddylist;
    }

    public function setBuddylist(?string $buddylist): void
    {
        $this->buddylist = $buddylist;
    }

    public function getIgnorelist(): ?string
    {
        return $this->ignorelist;
    }

    public function setIgnorelist(?string $ignorelist): void
    {
        $this->ignorelist = $ignorelist;
    }

    public function getNotepad(): ?string
    {
        return $this->notepad;
    }

    public function setNotepad(?string $notepad): void
    {
        $this->notepad = $notepad;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->uid;
    }

    public function getRoles(): array
    {
        $gid = $this->displaygroup > 0 ? $this->displaygroup : $this->usergroup;

        if ($gid === 7) {
            return ['ROLE_BANNED'];
        }

        $roles = ['ROLE_USER'];

        if ($gid === 4) {
            $roles[] = 'ROLE_ADMIN';
        } elseif (in_array($gid, [3, 6], true)) {
            $roles[] = 'ROLE_MODERATOR';
        }

        return $roles;
    }

    public function eraseCredentials(): void
    {
    }
}
