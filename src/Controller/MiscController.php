<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MiscController extends AbstractController
{
    #[Route('/misc.php', name: 'app_misc', priority: 100)]
    public function __invoke(
        Request $request,
        ForumRepository $forumRepository,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
        Connection $connection,
        EntityManagerInterface $em,
    ): Response {
        $action = $request->query->getString('action', '');

        if ($action === 'dstswitch') {
            return $this->dstswitch($request, $em);
        }
        if ($action === 'clearpass') {
            return $this->clearpass($request);
        }
        if ($action === 'do_helpsearch' && $request->isMethod('POST')) {
            return $this->doHelpsearch($request, $connection, $em);
        }
        if ($action === 'helpresults') {
            return $this->helpresults($request, $connection, $em);
        }
        if ($action === 'buddypopup') {
            return $this->buddypopup($request, $connection, $em);
        }

        return match ($action) {
            'rules' => $this->rules($request, $forumRepository),
            'whoposted' => $this->whoposted($request, $threadRepository, $postRepository),
            'smilies' => $this->smilies($request, $connection),
            'markread' => $this->markread($request, $em),
            'clearcookies' => $this->clearcookies($request),
            'help' => $this->help(),
            'syndication' => $this->redirectToRoute('app_syndication'),
            default => $this->redirectToRoute('app_board_index'),
        };
    }

    private function help(): Response
    {
        return $this->render('default/misc/help.html.twig');
    }

    private function markread(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $token = $request->request->getString('_token', '') ?: $request->query->getString('_token', '');
        if ($token !== '' && !$this->isCsrfTokenValid('markread', $token)) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser === null) {
            return $this->redirectToRoute('app_board_index');
        }

        $user = $em->getRepository(\App\Entity\User::class)->find($currentUser->getUid());
        if ($user === null) {
            return $this->redirectToRoute('app_board_index');
        }

        $now = time();
        $user->setLastvisit($now);
        $user->setLastactive($now);
        $em->flush();

        try {
            $conn = $em->getConnection();
            $forums = $conn->fetchAllAssociative('SELECT fid FROM mybb_forums WHERE type = ?', ['f']);
            foreach ($forums as $forum) {
                $fid = (int) $forum['fid'];
                $conn->executeStatement(
                    'REPLACE INTO mybb_forumsread (fid, uid, dateline) VALUES (:fid, :uid, :dateline)',
                    ['fid' => $fid, 'uid' => $user->getUid(), 'dateline' => $now]
                );
            }
        } catch (\Throwable) {
            // mybb_forumsread might not exist
        }

        $this->addFlash('success', 'Tum forumlar okundu olarak isaretlendi.');
        return $this->redirectToRoute('app_board_index');
    }

    private function clearcookies(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->redirectToRoute('app_board_index');
        }
        $token = $request->request->getString('_token', '') ?: $request->query->getString('_token', '');
        if ($token === '' || !$this->isCsrfTokenValid('clearcookies', $token)) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }
        $cookieNames = [
            'mybbuser', 'mybb[announcements]', 'mybb[lastvisit]', 'mybb[lastactive]',
            'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin',
            'mybblang', 'mybbtheme', 'multiquote', 'mybb[readallforums]', 'coppauser', 'coppadob', 'mybb[referrer]',
        ];
        $response = $this->redirectToRoute('app_board_index');
        foreach ($cookieNames as $name) {
            $response->headers->clearCookie($name, '/', null, false, true, 'lax');
        }
        $this->addFlash('success', 'Cerezler temizlendi.');
        return $response;
    }

    private function rules(Request $request, ForumRepository $forumRepository): Response
    {
        $fid = $request->query->getInt('fid');
        if ($fid <= 0) {
            throw $this->createNotFoundException('Forum ID gerekli.');
        }

        $forum = $forumRepository->find($fid);
        if ($forum === null || $forum->getType() !== 'f') {
            throw $this->createNotFoundException('Forum bulunamadi.');
        }

        $rules = $forum->getRules();
        if ($rules === null || $rules === '') {
            throw $this->createNotFoundException('Bu forumun kurallari yok.');
        }

        $rulestitle = $forum->getRulestitle() ?: $forum->getName() . ' - Kurallar';

        return $this->render('default/misc/rules.html.twig', [
            'forum' => $forum,
            'rules' => $rules,
            'rulestitle' => $rulestitle,
        ]);
    }

    private function whoposted(Request $request, ThreadRepository $threadRepository, PostRepository $postRepository): Response
    {
        $tid = $request->query->getInt('tid');
        if ($tid <= 0) {
            throw $this->createNotFoundException('Konu ID gerekli.');
        }

        $thread = $threadRepository->find($tid);
        if ($thread === null || $thread->getVisible() !== 1) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }

        $sortByUsername = $request->query->getString('sort') === 'username';
        $posters = $postRepository->findWhoPostedInThread($tid, $sortByUsername);
        $totalPosts = array_sum(array_column($posters, 'posts'));

        $modal = $request->query->getInt('modal') === 1;

        return $this->render($modal ? 'default/misc/whoposted_modal.html.twig' : 'default/misc/whoposted.html.twig', [
            'thread' => $thread,
            'posters' => $posters,
            'total_posts' => $totalPosts,
        ]);
    }

    private function smilies(Request $request, Connection $connection): Response
    {
        $smilies = [];
        try {
            $rows = $connection->fetchAllAssociative(
                'SELECT sid, name, image, find FROM mybb_smilies ORDER BY disporder ASC'
            );
            foreach ($rows as $row) {
                $find = $row['find'];
                if (str_contains($find, "\n")) {
                    $find = explode("\n", $find)[0];
                }
                $smilies[] = [
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'find' => trim($find),
                ];
            }
        } catch (\Throwable) {
            // Table might not exist
        }

        $popup = $request->query->getInt('popup') === 1;
        $editor = $request->query->getString('editor', 'message');

        return $this->render($popup ? 'default/misc/smilies_popup.html.twig' : 'default/misc/smilies.html.twig', [
            'smilies' => $smilies,
            'editor' => preg_replace('/[^a-zA-Z0-9_-]/', '', $editor),
        ]);
    }

    private function dstswitch(Request $request, EntityManagerInterface $em): Response
    {
        if (!$request->isMethod('POST') || !$this->getUser()) {
            return $this->redirectToRoute('app_board_index');
        }
        $user = $em->getRepository(\App\Entity\User::class)->find($this->getUser()->getUid());
        if ($user === null) {
            return $this->redirectToRoute('app_board_index');
        }
        $user->setDst($user->getDst() === 1 ? 0 : 1);
        $em->flush();
        $this->addFlash('success', 'Yaz/kış saati ayarı güncellendi.');
        if ($request->request->getBoolean('ajax')) {
            return new Response('done', 200, ['Content-Type' => 'text/plain']);
        }
        return $this->redirectToRoute('app_board_index');
    }

    private function clearpass(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->redirectToRoute('app_board_index');
        }
        $fid = $request->request->getInt('fid', 0) ?: $request->query->getInt('fid', 0);
        if ($fid <= 0) {
            $this->addFlash('error', 'Forum ID gerekli.');
            return $this->redirectToRoute('app_board_index');
        }
        if (!$this->isCsrfTokenValid('clearpass', $request->request->getString('_token', '') ?: $request->query->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }
        $response = $this->redirectToRoute('app_board_index');
        $response->headers->clearCookie('forumpass[' . $fid . ']', '/', null, false, true, 'lax');
        $this->addFlash('success', 'Forum şifresi çerezden kaldırıldı.');
        return $response;
    }

    private function doHelpsearch(Request $request, Connection $connection, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('do_helpsearch', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_misc', ['action' => 'help']);
        }
        $keywords = trim($request->request->getString('keywords', ''));
        $searchName = $request->request->getInt('name', 0);
        $searchDocument = $request->request->getInt('document', 0);
        if ($searchName !== 1 && $searchDocument !== 1) {
            $this->addFlash('error', 'İsim veya içerik araması seçin.');
            return $this->redirectToRoute('app_misc', ['action' => 'help']);
        }
        $uid = $this->getUser() ? $this->getUser()->getUid() : 0;
        $sid = bin2hex(random_bytes(16));
        $querycache = '';
        try {
            $helpdocs = $connection->fetchAllAssociative('SELECT hid, name, document FROM mybb_helpdocs WHERE enabled = 1');
            $matched = [];
            $kw = mb_strtolower($keywords);
            foreach ($helpdocs as $doc) {
                $match = ($searchName === 1 && $kw !== '' && mb_strpos(mb_strtolower($doc['name']), $kw) !== false)
                    || ($searchDocument === 1 && $kw !== '' && mb_strpos(mb_strtolower($doc['document'] ?? ''), $kw) !== false);
                if ($match) {
                    $matched[] = $doc['hid'];
                }
            }
            $querycache = implode(',', $matched);
            $conn = $em->getConnection();
            $conn->insert('mybb_searchlog', [
                'sid' => $sid,
                'uid' => $uid,
                'dateline' => time(),
                'ipaddress' => $request->getClientIp(),
                'threads' => '',
                'posts' => '',
                'resulttype' => $searchDocument === 1 ? 'helpdoc' : 'helpname',
                'querycache' => $querycache,
                'keywords' => $keywords,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Yardım araması şu an kullanılamıyor.');
            return $this->redirectToRoute('app_misc', ['action' => 'help']);
        }
        $this->addFlash('success', 'Arama tamamlandı.');
        return $this->redirectToRoute('app_misc', ['action' => 'helpresults', 'sid' => $sid]);
    }

    private function helpresults(Request $request, Connection $connection, EntityManagerInterface $em): Response
    {
        $sid = $request->query->getString('sid', '');
        if ($sid === '') {
            throw $this->createNotFoundException('Arama oturumu bulunamadi.');
        }
        $uid = $this->getUser() ? $this->getUser()->getUid() : 0;
        try {
            $search = $connection->fetchAssociative(
                'SELECT * FROM mybb_searchlog WHERE sid = ? AND uid = ?',
                [$sid, $uid],
                [\PDO::PARAM_STR, \PDO::PARAM_INT]
            );
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('Arama bulunamadi.');
        }
        if (!$search) {
            throw $this->createNotFoundException('Arama bulunamadi.');
        }
        $querycache = $search['querycache'] ?? '';
        $helpdocs = [];
        $perPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $perPage;
        if ($querycache !== '') {
            $ids = array_map('intval', array_filter(explode(',', $querycache)));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $limit = (int) $perPage;
                $off = (int) $offset;
                $rows = $connection->fetchAllAssociative(
                    'SELECT h.* FROM mybb_helpdocs h INNER JOIN mybb_helpsections s ON s.sid = h.sid WHERE h.hid IN (' . $placeholders . ') AND h.enabled = 1 AND s.enabled = 1 ORDER BY h.sid, h.disporder LIMIT ' . $limit . ' OFFSET ' . $off,
                    $ids,
                    array_fill(0, count($ids), \PDO::PARAM_INT)
                );
                foreach ($rows as $r) {
                    $helpdocs[] = [
                        'hid' => (int) $r['hid'],
                        'name' => $r['name'],
                        'helpdoc' => mb_strlen($r['document'] ?? '') > 350 ? mb_substr(strip_tags($r['document']), 0, 350) . '...' : ($r['document'] ?? ''),
                    ];
                }
            }
        }
        $total = $querycache === '' ? 0 : count(array_filter(explode(',', $querycache)));
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        return $this->render('default/misc/helpresults.html.twig', [
            'sid' => $sid,
            'keywords' => $search['keywords'] ?? '',
            'helpdocs' => $helpdocs,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    private function buddypopup(Request $request, Connection $connection, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $em->getRepository(\App\Entity\User::class)->find($this->getUser()->getUid());
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }
        $buddylist = $user->getBuddylist();
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('buddypopup', $request->request->getString('_token', ''))) {
            $removeUid = $request->request->getInt('removebuddy', 0);
            if ($removeUid > 0 && $buddylist !== null && $buddylist !== '') {
                $ids = array_map('intval', array_filter(explode(',', $buddylist)));
                $ids = array_values(array_diff($ids, [$removeUid]));
                $user->setBuddylist($ids === [] ? null : implode(',', $ids));
                $em->flush();
                $buddylist = $user->getBuddylist();
            }
        }
        $buddiesOnline = [];
        $buddiesOffline = [];
        $wolCutoff = time() - 900; // 15 min
        if ($buddylist !== null && $buddylist !== '') {
            $uids = array_filter(array_map('intval', explode(',', $buddylist)));
            if (!empty($uids)) {
                $placeholders = implode(',', array_fill(0, count($uids), '?'));
                $rows = $connection->fetchAllAssociative(
                    'SELECT uid, username, usergroup, displaygroup, lastactive, lastvisit, invisible, receivepms FROM mybb_users WHERE uid IN (' . $placeholders . ') ORDER BY lastactive DESC',
                    $uids,
                    array_fill(0, count($uids), \PDO::PARAM_INT)
                );
                foreach ($rows as $row) {
                    $buddy = [
                        'uid' => (int) $row['uid'],
                        'username' => $row['username'],
                        'usergroup' => (int) $row['usergroup'],
                        'displaygroup' => (int) ($row['displaygroup'] ?? 0),
                        'lastactive' => (int) ($row['lastactive'] ?? 0),
                        'lastvisit' => (int) ($row['lastvisit'] ?? 0),
                        'invisible' => (int) ($row['invisible'] ?? 0),
                        'receivepms' => (int) ($row['receivepms'] ?? 1),
                    ];
                    $isOnline = $buddy['lastactive'] > $wolCutoff && ($buddy['invisible'] === 0 || $user->getUsergroup() === 4) && $buddy['lastvisit'] != $buddy['lastactive'];
                    if ($isOnline) {
                        $buddiesOnline[] = $buddy;
                    } else {
                        $buddiesOffline[] = $buddy;
                    }
                }
            }
        }
        return $this->render('default/misc/buddypopup.html.twig', [
            'buddies_online' => $buddiesOnline,
            'buddies_offline' => $buddiesOffline,
            'has_buddies' => !empty($buddiesOnline) || !empty($buddiesOffline),
        ]);
    }
}
