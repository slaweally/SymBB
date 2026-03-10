<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Thread;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Repository\ReportedContentRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModCPController extends AbstractController
{
    #[Route('/modcp.php', name: 'app_modcp', priority: 100)]
    public function __invoke(
        Request $request,
        ReportedContentRepository $reportedContentRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $action = $request->query->getString('action', '') ?: $request->request->getString('action', '');

        if ($action === 'resolve_report' && $request->isMethod('POST') && $request->request->has('rid')) {
            $rid = $request->request->getInt('rid');
            if ($rid > 0 && $this->isCsrfTokenValid('modcp_resolve_' . $rid, $request->request->getString('_token'))) {
                $report = $reportedContentRepository->find($rid);
                if ($report !== null) {
                    $em->remove($report);
                    $em->flush();
                    $this->addFlash('success', 'Rapor işlendi.');
                }
            }
            return $this->redirectToRoute('app_modcp');
        }

        if ($action === 'ban' && $request->isMethod('POST') && $request->request->has('target')) {
            $target = trim($request->request->getString('target') ?? '');
            if ($target !== '' && $this->isCsrfTokenValid('modcp_ban', $request->request->getString('_token'))) {
                $user = null;
                if (is_numeric($target)) {
                    $user = $userRepository->find((int) $target);
                } else {
                    $user = $userRepository->findOneBy(['username' => $target]);
                }
                if ($user !== null) {
                    $user->setUsergroup(7);
                    $em->flush();
                    $this->addFlash('success', sprintf('%s kullanıcısı banlandı.', $user->getUsername()));
                } else {
                    $this->addFlash('error', 'Kullanıcı bulunamadı.');
                }
            }
            return $this->redirectToRoute('app_modcp');
        }

        if ($action === 'ipsearch') {
            return $this->ipsearch($request, $em);
        }
        if ($action === 'merge') {
            return $this->mergeThreads($request, $em, $threadRepository, $postRepository);
        }
        if ($action === 'split') {
            return $this->splitThread($request, $em, $threadRepository, $postRepository);
        }
        if ($action === 'purgespammer') {
            return $this->purgespammer($request, $em, $userRepository, $postRepository, $threadRepository);
        }

        $reports = $reportedContentRepository->createQueryBuilder('r')
            ->where("r.type = 'post'")
            ->orderBy('r.dateline', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('default/modcp/index.html.twig', [
            'reports' => $reports,
        ]);
    }

    private function ipsearch(Request $request, EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        $ip = trim($request->query->getString('ipaddress', ''));
        $users = [];
        $posts = [];
        $hasRegip = false;
        $hasLastip = false;
        $hasPostIp = false;
        try {
            $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'regip'");
            $hasRegip = !empty($cols);
            $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'lastip'");
            $hasLastip = !empty($cols);
            $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_posts LIKE 'ipaddress'");
            $hasPostIp = !empty($cols);
        } catch (\Throwable $e) {
        }
        if ($ip !== '' && filter_var($ip, \FILTER_VALIDATE_IP)) {
            if ($hasRegip || $hasLastip) {
                $where = [];
                $params = [];
                if ($hasRegip) {
                    $where[] = 'regip = ?';
                    $params[] = $ip;
                }
                if ($hasLastip) {
                    $where[] = 'lastip = ?';
                    $params[] = $ip;
                }
                $sql = 'SELECT uid, username' . ($hasRegip ? ', regip' : '') . ($hasLastip ? ', lastip' : '') . ' FROM mybb_users WHERE ' . implode(' OR ', $where);
                $users = $conn->fetchAllAssociative($sql, $params);
            }
            if ($hasPostIp) {
                $posts = $conn->fetchAllAssociative(
                    'SELECT p.pid, p.tid, p.uid, p.username, p.subject, p.ipaddress FROM mybb_posts p WHERE p.ipaddress = ? ORDER BY p.dateline DESC LIMIT 100',
                    [$ip],
                    [\PDO::PARAM_STR]
                );
            }
        }
        return $this->render('default/modcp/ipsearch.html.twig', [
            'ipaddress' => $ip,
            'users' => $users,
            'posts' => $posts,
        ]);
    }

    private function mergeThreads(Request $request, EntityManagerInterface $em, ThreadRepository $threadRepository, PostRepository $postRepository): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('modcp_merge', $request->request->getString('_token', ''))) {
            $mergetid = $request->request->getInt('mergetid', 0);
            $tid = $request->request->getInt('tid', 0);
            if ($mergetid <= 0 || $tid <= 0 || $mergetid === $tid) {
                $this->addFlash('error', 'Geçersiz konu ID.');
                return $this->redirectToRoute('app_modcp', ['action' => 'merge']);
            }
            $sourceThread = $threadRepository->find($mergetid);
            $destThread = $threadRepository->find($tid);
            if ($sourceThread === null || $destThread === null) {
                $this->addFlash('error', 'Konu bulunamadı.');
                return $this->redirectToRoute('app_modcp', ['action' => 'merge']);
            }
            $sourcePosts = $postRepository->findBy(['thread' => $sourceThread], ['dateline' => 'ASC']);
            $moved = 0;
            foreach ($sourcePosts as $post) {
                $post->setThread($destThread);
                $em->persist($post);
                $moved++;
            }
            $em->flush();
            $newReplies = $destThread->getReplies() + $moved;
            $destThread->setReplies($newReplies);
            $conn = $em->getConnection();
            $lastpost = $conn->fetchOne('SELECT COALESCE(MAX(dateline), 0) FROM mybb_posts WHERE tid = ?', [$tid]);
            $destThread->setLastpost((int) $lastpost);
            $em->remove($sourceThread);
            $em->flush();
            $this->addFlash('success', 'Konular birleştirildi.');
            return $this->redirectToRoute('app_forum_display', ['id' => $destThread->getFid()]);
        }
        return $this->render('default/modcp/merge.html.twig');
    }

    private function splitThread(Request $request, EntityManagerInterface $em, ThreadRepository $threadRepository, PostRepository $postRepository): Response
    {
        $tid = $request->query->getInt('tid', 0) ?: $request->request->getInt('tid', 0);
        if ($tid <= 0) {
            return $this->render('default/modcp/split.html.twig', ['thread' => null, 'posts' => []]);
        }
        $thread = $threadRepository->find($tid);
        if ($thread === null) {
            $this->addFlash('error', 'Konu bulunamadı.');
            return $this->redirectToRoute('app_modcp');
        }
        $posts = $postRepository->findBy(['thread' => $thread], ['dateline' => 'ASC']);
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('modcp_split', $request->request->getString('_token', ''))) {
            $pids = array_filter(array_map('intval', (array) $request->request->all('pids')));
            $newSubject = trim($request->request->getString('newsubject', ''));
            if (empty($pids) || $newSubject === '') {
                $this->addFlash('error', 'En az bir mesaj seçin ve yeni konu başlığı girin.');
                return $this->render('default/modcp/split.html.twig', ['thread' => $thread, 'posts' => $posts]);
            }
            $postsToMove = $postRepository->findBy(['pid' => $pids]);
            $first = null;
            $minDateline = PHP_INT_MAX;
            foreach ($postsToMove as $p) {
                if ($p->getThread()->getTid() !== $thread->getTid()) {
                    continue;
                }
                if ($p->getDateline() < $minDateline) {
                    $minDateline = $p->getDateline();
                    $first = $p;
                }
            }
            if ($first === null) {
                $this->addFlash('error', 'Seçilen mesajlar bu konuda değil.');
                return $this->render('default/modcp/split.html.twig', ['thread' => $thread, 'posts' => $posts]);
            }
            $newThread = new Thread();
            $newThread->setFid($thread->getFid());
            $newThread->setSubject($newSubject);
            $newThread->setUid($first->getUid());
            $newThread->setUsername($first->getUsername());
            $newThread->setDateline($first->getDateline());
            $newThread->setLastpost($first->getDateline());
            $newThread->setReplies(0);
            $newThread->setViews(0);
            $newThread->setVisible($thread->getVisible());
            $newThread->setClosed('');
            $newThread->setStickied(0);
            $newThread->setPoll(0);
            $newThread->setNumratings(0);
            $newThread->setTotalratings(0);
            $newThread->setNotes('');
            $em->persist($newThread);
            $em->flush();
            $count = 0;
            foreach ($postsToMove as $p) {
                if ($p->getThread()->getTid() !== $thread->getTid()) {
                    continue;
                }
                $p->setThread($newThread);
                $p->setSubject($p->getPid() === $first->getPid() ? $newSubject : 'Re: ' . $newSubject);
                $em->persist($p);
                $count++;
            }
            $newThread->setReplies(max(0, $count - 1));
            $em->flush();
            $remaining = $thread->getReplies() - $count;
            $thread->setReplies(max(0, $remaining));
            $conn = $em->getConnection();
            $lastpost = $conn->fetchOne('SELECT COALESCE(MAX(dateline), 0) FROM mybb_posts WHERE tid = ?', [$thread->getTid()]);
            $thread->setLastpost((int) $lastpost);
            $em->flush();
            $this->addFlash('success', 'Konu ayrıştırıldı.');
            return $this->redirectToRoute('app_thread_show', ['id' => $newThread->getTid()]);
        }
        return $this->render('default/modcp/split.html.twig', ['thread' => $thread, 'posts' => $posts]);
    }

    private function purgespammer(Request $request, EntityManagerInterface $em, UserRepository $userRepository, PostRepository $postRepository, ThreadRepository $threadRepository): Response
    {
        $uid = $request->query->getInt('uid', 0) ?: $request->request->getInt('uid', 0);
        $user = $uid > 0 ? $userRepository->find($uid) : null;
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('modcp_purge', $request->request->getString('_token', ''))) {
            $uid = $request->request->getInt('uid', 0);
            $ban = $request->request->getBoolean('ban', true);
            if ($uid <= 0) {
                $this->addFlash('error', 'Geçersiz kullanıcı.');
                return $this->redirectToRoute('app_modcp', ['action' => 'purgespammer']);
            }
            $target = $userRepository->find($uid);
            if ($target === null) {
                $this->addFlash('error', 'Kullanıcı bulunamadı.');
                return $this->redirectToRoute('app_modcp', ['action' => 'purgespammer']);
            }
            /** @var User $current */
            $current = $this->getUser();
            if ($target->getUid() === $current->getUid()) {
                $this->addFlash('error', 'Kendinize bu işlemi uygulayamazsınız.');
                return $this->redirectToRoute('app_modcp', ['action' => 'purgespammer']);
            }
            $posts = $postRepository->findBy(['uid' => $uid]);
            foreach ($posts as $post) {
                $em->remove($post);
            }
            $threads = $threadRepository->findBy(['uid' => $uid]);
            foreach ($threads as $t) {
                $em->remove($t);
            }
            if ($ban) {
                $target->setUsergroup(7);
                $target->setDisplaygroup(7);
            } else {
                $em->remove($target);
            }
            $em->flush();
            $this->addFlash('success', $ban ? 'Kullanıcı içeriği silindi ve banlandı.' : 'Kullanıcı ve içeriği silindi.');
            return $this->redirectToRoute('app_modcp');
        }
        return $this->render('default/modcp/purgespammer.html.twig', ['user' => $user]);
    }
}
