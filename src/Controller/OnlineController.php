<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/online.php', name: 'app_online', priority: 100)]
final class OnlineController extends AbstractController
{
    private const WOL_CUTOFF_MINUTES = 15;

    public function __construct(
        private readonly Connection $connection,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $cutoff = time() - (self::WOL_CUTOFF_MINUTES * 60);
        $users = [];
        $useSessions = false;

        try {
            $sm = $this->connection->createSchemaManager();
            $tables = $sm->listTableNames();
            if (in_array('mybb_sessions', $tables, true)) {
                $columns = $sm->listTableColumns('mybb_sessions');
                $timeCol = isset($columns['time']) ? 'time' : (isset($columns['lastactive']) ? 'lastactive' : null);
                if ($timeCol !== null) {
                    $rows = $this->connection->fetchAllAssociative(
                        "SELECT s.sid, s.ip, s.uid, s.{$timeCol} AS lastactive, s.location, u.username, u.invisible, u.usergroup, u.displaygroup
                         FROM mybb_sessions s
                         LEFT JOIN mybb_users u ON s.uid = u.uid
                         WHERE s.{$timeCol} > :cutoff
                         ORDER BY s.{$timeCol} DESC",
                        ['cutoff' => $cutoff]
                    );
                    if (count($rows) > 0) {
                        $useSessions = true;
                        foreach ($rows as $row) {
                            $uid = (int) ($row['uid'] ?? 0);
                            $users[] = [
                                'uid' => $uid,
                                'username' => $row['username'] ?? 'Misafir',
                                'lastactive' => (int) ($row['lastactive'] ?? 0),
                                'location' => $this->mapLocation($row['location'] ?? ''),
                                'ip' => $row['ip'] ?? null,
                                'invisible' => (int) ($row['invisible'] ?? 0),
                                'usergroup' => (int) ($row['usergroup'] ?? 0),
                                'displaygroup' => (int) ($row['displaygroup'] ?? 0),
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        if (!$useSessions) {
            $activeUsers = $this->userRepository->findActiveUsers($cutoff);
            foreach ($activeUsers as $u) {
                $loc = $u->getLocation();
                $users[] = [
                    'uid' => $u->getUid(),
                    'username' => $u->getUsername(),
                    'lastactive' => $u->getLastactive() ?? 0,
                    'location' => $loc !== null && $loc !== '' ? $this->mapLocation($loc) : 'Bilinmiyor',
                    'ip' => null,
                    'invisible' => $u->getInvisible(),
                    'usergroup' => $u->getUsergroup(),
                    'displaygroup' => $u->getDisplaygroup() ?: $u->getUsergroup(),
                ];
            }
        }

        $canViewIp = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_MODERATOR');
        $canViewInvisible = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_MODERATOR');

        $filtered = [];
        foreach ($users as $u) {
            if ($u['invisible'] === 1 && !$canViewInvisible) {
                continue;
            }
            $filtered[] = $u;
        }

        return $this->render('default/online/index.html.twig', [
            'users' => $filtered,
            'can_view_ip' => $canViewIp,
            'can_view_invisible' => $canViewInvisible,
        ]);
    }

    private function mapLocation(string $location): string
    {
        if ($location === '' || $location === 'nopermission') {
            return 'İzin yok';
        }
        $loc = strtolower($location);
        $map = [
            'app_board_index' => 'Ana Sayfa',
            'app_forum_display' => 'Forum Görüntülüyor',
            'app_thread_show' => 'Konu Görüntülüyor',
            'app_thread_reply' => 'Cevap Yazıyor',
            'app_thread_new' => 'Yeni Konu Yazıyor',
            'app_post_edit' => 'Mesaj Düzenliyor',
            'app_member' => 'Üye Profili',
            'app_memberlist' => 'Üye Listesi',
            'app_search' => 'Arama Yapıyor',
            'app_private_message' => 'Özel Mesajlar',
            'app_usercp' => 'Kullanıcı Paneli',
            'app_online' => 'Kimler Çevrimiçi',
            'app_misc' => 'Çeşitli',
            'app_modcp' => 'Moderatör Paneli',
            'app_portal' => 'Portal',
            'app_stats' => 'İstatistikler',
            'app_showteam' => 'Yönetim Kadrosu',
        ];
        foreach ($map as $key => $label) {
            if (str_contains($loc, $key)) {
                return $label;
            }
        }
        return 'Forumda gezinme';
    }
}
