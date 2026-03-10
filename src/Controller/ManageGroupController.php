<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManageGroupController extends AbstractController
{
    #[Route('/managegroup.php', name: 'app_managegroup', priority: 100)]
    public function __invoke(Request $request, Connection $connection): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $gid = $request->query->getInt('gid', 0);
        if ($gid <= 0) {
            return $this->render('default/managegroup/index.html.twig', ['group' => null, 'error' => 'Geçersiz grup ID.']);
        }
        try {
            $group = $connection->fetchAssociative('SELECT gid, title FROM mybb_usergroups WHERE gid = ?', [$gid], [\PDO::PARAM_INT]);
        } catch (\Throwable $e) {
            $group = null;
        }
        if ($group === false || $group === null) {
            return $this->render('default/managegroup/index.html.twig', ['group' => null, 'error' => 'Grup bulunamadı.']);
        }
        $action = $request->query->getString('action', '');
        if ($action === 'joinrequests' && $request->isMethod('POST') && $this->isCsrfTokenValid('managegroup_join', $request->request->getString('_token', ''))) {
            return $this->handleJoinRequest($request, $connection, $gid);
        }
        $joinRequests = [];
        try {
            $joinRequests = $connection->fetchAllAssociative(
                'SELECT j.uid, j.dateline, u.username FROM mybb_joinrequests j LEFT JOIN mybb_users u ON u.uid = j.uid WHERE j.gid = ? ORDER BY j.dateline DESC',
                [$gid],
                [\PDO::PARAM_INT]
            );
        } catch (\Throwable $e) {
        }
        return $this->render('default/managegroup/index.html.twig', [
            'group' => $group,
            'join_requests' => $joinRequests,
            'gid' => $gid,
        ]);
    }

    private function handleJoinRequest(Request $request, Connection $connection, int $gid): Response
    {
        $uid = $request->request->getInt('uid', 0);
        $accept = $request->request->getBoolean('accept', true);
        if ($uid <= 0) {
            return $this->redirectToRoute('app_managegroup', ['gid' => $gid]);
        }
        try {
            $connection->delete('mybb_joinrequests', ['uid' => $uid, 'gid' => $gid]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Katılım isteği işlenemedi.');
            return $this->redirectToRoute('app_managegroup', ['gid' => $gid]);
        }
        if ($accept) {
            try {
                $user = $connection->fetchAssociative('SELECT uid, additionalgroups FROM mybb_users WHERE uid = ?', [$uid], [\PDO::PARAM_INT]);
                if ($user) {
                    $groups = array_filter(array_map('intval', explode(',', (string) ($user['additionalgroups'] ?? ''))));
                    if (!in_array($gid, $groups, true)) {
                        $groups[] = $gid;
                        $connection->update('mybb_users', ['additionalgroups' => implode(',', $groups)], ['uid' => $uid]);
                    }
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Kullanıcı gruba eklenemedi.');
            }
            $this->addFlash('success', 'Katılım isteği kabul edildi.');
        } else {
            $this->addFlash('notice', 'Katılım isteği reddedildi.');
        }
        return $this->redirectToRoute('app_managegroup', ['gid' => $gid, 'action' => 'joinrequests']);
    }
}
