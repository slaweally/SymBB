<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnouncementController extends AbstractController
{
    #[Route('/announcements.php', name: 'app_announcements', priority: 100)]
    public function __invoke(Request $request, Connection $connection): Response
    {
        $aid = $request->query->getInt('aid', 0);
        if ($aid <= 0) {
            throw $this->createNotFoundException('Duyuru ID gerekli.');
        }
        try {
            $now = time();
            $row = $connection->fetchAssociative(
                'SELECT a.*, u.username FROM mybb_announcements a LEFT JOIN mybb_users u ON u.uid = a.uid WHERE a.aid = ? AND a.startdate <= ? AND (a.enddate >= ? OR a.enddate = 0)',
                [$aid, $now, $now],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            );
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('Duyuru bulunamadi.');
        }
        if (!$row) {
            throw $this->createNotFoundException('Duyuru bulunamadi.');
        }
        $fid = (int) $row['fid'];
        if ($fid > 0) {
            try {
                $forum = $connection->fetchAssociative('SELECT fid, name FROM mybb_forums WHERE fid = ? AND type = ?', [$fid, 'f'], [\PDO::PARAM_INT, \PDO::PARAM_STR]);
                if (!$forum) {
                    throw $this->createNotFoundException('Forum bulunamadi.');
                }
            } catch (\Throwable $e) {
                $forum = null;
            }
        } else {
            $forum = null;
        }
        return $this->render('default/announcement/view.html.twig', [
            'announcement' => [
                'aid' => (int) $row['aid'],
                'subject' => $row['subject'] ?? '',
                'message' => $row['message'] ?? '',
                'username' => $row['username'] ?? '',
                'uid' => (int) ($row['uid'] ?? 0),
                'startdate' => (int) ($row['startdate'] ?? 0),
                'enddate' => (int) ($row['enddate'] ?? 0),
            ],
            'forum' => $forum,
        ]);
    }
}
