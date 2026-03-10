<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Post;
use App\Entity\Thread;
use App\Entity\User;
use App\Entity\ThreadSubscription;
use App\Repository\ForumRepository;
use App\Repository\PollRepository;
use App\Repository\ThreadRatingRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadSubscriptionRepository;
use App\Service\AttachmentUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ThreadController extends AbstractController
{
    #[Route('/showthread.php', name: 'app_thread_show', priority: 100)]
    public function show(
        Request $request,
        ThreadRepository $threadRepository,
        ForumRepository $forumRepository,
        ThreadSubscriptionRepository $subscriptionRepository,
        ThreadRatingRepository $ratingRepository,
        PollRepository $pollRepository,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response {
        $tid = $request->query->getInt('tid');

        if ($tid <= 0) {
            throw $this->createNotFoundException('Thread ID gerekli.');
        }

        // ThreadRepository->findWithPosts metodunu paginator icin queryBuilder ile degistirebiliriz.
        // Fakat basitce Thread'in kendisini cekelim.
        $thread = $threadRepository->find($tid);

        if ($thread === null) {
            throw $this->createNotFoundException(
                sprintf('Konu bulunamadi: tid=%d', $tid)
            );
        }

        if ($thread->getVisible() === -1 && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Bu konu silinmis.');
        }

        $page = $request->query->getInt('page', 1);
        $limit = 20;

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user !== null && $user->getPpp() > 0) {
            $limit = $user->getPpp();
        }

        $queryBuilder = $em->getRepository(Post::class)->createQueryBuilder('p')
            ->leftJoin('p.attachments', 'a')->addSelect('a')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread);
        if (!$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            $queryBuilder->andWhere('p.visible = 1');
        } else {
            $queryBuilder->andWhere('p.visible IN (1, -1)');
        }
        $queryBuilder->orderBy('p.dateline', 'ASC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $page,
            $limit
        );

        // Kullanicilari eager loading icin topla
        $uids = [];
        foreach ($pagination->getItems() as $post) {
            if ($post->getUid() > 0) {
                $uids[] = $post->getUid();
            }
        }

        if (count($uids) > 0) {
            $users = $em->getRepository(User::class)->findBy(['uid' => array_unique($uids)]);

            $userMap = [];
            foreach ($users as $u) {
                $userMap[$u->getUid()] = $u;
            }

            foreach ($pagination->getItems() as $post) {
                if (isset($userMap[$post->getUid()])) {
                    $post->setUser($userMap[$post->getUid()]);
                }
            }
        }

        $poll = null;
        $userHasVoted = false;
        $canVote = false;
        if ($thread->getPoll() > 0) {
            $poll = $pollRepository->find($thread->getPoll());
            if ($poll !== null && $user !== null) {
                $userHasVoted = $em->getRepository(\App\Entity\PollVote::class)
                    ->hasUserVoted($poll->getPid() ?? 0, $user->getUid() ?? 0);
                $canVote = !$userHasVoted && $thread->getClosed() === '' && $poll->getClosed() === 0 && !$poll->isExpired();
            }
        }

        $isSubscribed = false;
        if ($user !== null) {
            $isSubscribed = $subscriptionRepository->findByUserAndThread($user->getUid(), $thread->getTid()) !== null;
        }

        $userHasRatedThread = false;
        if ($user !== null) {
            $userHasRatedThread = $ratingRepository->hasUserRated($thread->getTid(), $user->getUid());
        }

        $forum = $forumRepository->find($thread->getFid());

        return $this->render('default/thread/show.html.twig', [
            'thread' => $thread,
            'pagination' => $pagination,
            'poll' => $poll,
            'user_has_voted' => $userHasVoted,
            'can_vote' => $canVote,
            'is_subscribed' => $isSubscribed,
            'user_has_rated_thread' => $userHasRatedThread,
            'forum' => $forum,
        ]);
    }

    #[Route('/printthread.php', name: 'app_thread_print', priority: 100)]
    public function print(
        Request $request,
        ThreadRepository $threadRepository,
        EntityManagerInterface $em
    ): Response {
        $tid = $request->query->getInt('tid');

        if ($tid <= 0) {
            throw $this->createNotFoundException('Thread ID gerekli.');
        }

        $thread = $threadRepository->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException(sprintf('Konu bulunamadi: tid=%d', $tid));
        }

        if ($thread->getVisible() === -1 && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Bu konu silinmis.');
        }

        $queryBuilder = $em->getRepository(Post::class)->createQueryBuilder('p')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('p.dateline', 'ASC');
        if (!$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            $queryBuilder->andWhere('p.visible = 1');
        } else {
            $queryBuilder->andWhere('p.visible IN (1, -1)');
        }
        $posts = $queryBuilder->getQuery()->getResult();

        return $this->render('default/thread/print.html.twig', [
            'thread' => $thread,
            'posts' => $posts,
        ]);
    }

    #[Route('/thread/{tid}/subscribe', name: 'app_thread_subscribe', priority: 100, methods: ['POST'])]
    public function subscribe(
        int $tid,
        ThreadRepository $threadRepository,
        ThreadSubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $thread = $threadRepository->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $sub = $subscriptionRepository->findByUserAndThread($user->getUid(), $tid);

        if ($sub !== null) {
            $em->remove($sub);
            $em->flush();
            $this->addFlash('success', 'Abonelikten cikildi.');
        } else {
            $sub = new ThreadSubscription();
            $sub->setUid($user->getUid());
            $sub->setTid($tid);
            $sub->setNotification(0);
            $sub->setDateline(time());
            $em->persist($sub);
            $em->flush();
            $this->addFlash('success', 'Konuya abone olundu.');
        }

        return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
    }

    #[Route('/newthread.php', name: 'app_thread_new', priority: 100, methods: ['GET', 'POST'])]
    public function newThread(
        Request $request,
        ForumRepository $forumRepository,
        EntityManagerInterface $em,
        AttachmentUploadService $attachmentService,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            $this->addFlash('error', 'Konu acmak icin giris yapmaniz gerekiyor.');

            return $this->redirectToRoute('app_login');
        }

        $editDraftTid = $request->query->getInt('editdraft');
        $fid = $editDraftTid > 0 ? 0 : $request->query->getInt('fid');

        $draftThread = null;
        $draftPost = null;
        if ($editDraftTid > 0) {
            $draftThread = $em->getRepository(Thread::class)->find($editDraftTid);
            if ($draftThread === null || $draftThread->getVisible() !== 2 || $draftThread->getUid() !== $user->getUid()) {
                throw $this->createNotFoundException('Taslak bulunamadi.');
            }
            $posts = $draftThread->getPosts();
            $draftPost = $posts->first() ?: null;
            if ($draftPost === null) {
                $draftPost = $em->getRepository(Post::class)->createQueryBuilder('p')
                    ->where('p.thread = :thread')->setParameter('thread', $draftThread)
                    ->orderBy('p.dateline', 'ASC')->setMaxResults(1)
                    ->getQuery()->getOneOrNullResult();
            }
            if ($draftPost === null) {
                throw $this->createNotFoundException('Taslak icerigi bulunamadi.');
            }
            $fid = $draftThread->getFid();
        }

        if ($fid <= 0) {
            throw $this->createNotFoundException('Forum ID gerekli.');
        }

        $forum = $forumRepository->find($fid);

        if ($forum === null) {
            throw $this->createNotFoundException(
                sprintf('Forum bulunamadi: fid=%d', $fid)
            );
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $subject = trim($request->request->getString('subject'));
            $message = trim($request->request->getString('message'));
            $savedraft = $request->request->has('savedraft');
            $draftTid = $request->request->getInt('draft_tid');

            if ($draftTid > 0) {
                $draftThread = $em->getRepository(Thread::class)->find($draftTid);
                if ($draftThread !== null && $draftThread->getVisible() === 2 && $draftThread->getUid() === $user->getUid()) {
                    $draftPost = $em->getRepository(Post::class)->createQueryBuilder('p')
                        ->where('p.thread = :thread')->setParameter('thread', $draftThread)
                        ->orderBy('p.dateline', 'ASC')->setMaxResults(1)
                        ->getQuery()->getOneOrNullResult();
                } else {
                    $draftThread = null;
                    $draftPost = null;
                }
            }

            if ($savedraft) {
                $now = time();
                if ($draftThread !== null && $draftPost !== null) {
                    $draftThread->setSubject($subject ?: '(Taslak)');
                    $draftPost->setSubject($subject ?: '(Taslak)');
                    $draftPost->setMessage($message);
                    $draftPost->setDateline($now);
                    $em->flush();
                } else {
                    $thread = new Thread();
                    $thread->setFid($fid);
                    $thread->setSubject($subject ?: '(Taslak)');
                    $thread->setUid($user->getUid() ?? 0);
                    $thread->setUsername($user->getUsername());
                    $thread->setDateline($now);
                    $thread->setVisible(2);

                    $em->persist($thread);
                    $em->flush();

                    $post = new Post();
                    $post->setThread($thread);
                    $post->setUid($user->getUid() ?? 0);
                    $post->setUsername($user->getUsername());
                    $post->setSubject($subject ?: '(Taslak)');
                    $post->setMessage($message);
                    $post->setDateline($now);
                    $post->setVisible(2);

                    $em->persist($post);
                    $em->flush();
                }
                $this->addFlash('success', 'Taslak kaydedildi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'drafts']);
            }

            if ($subject === '' || $message === '') {
                $error = 'Konu basligi ve mesaj alanlari zorunludur.';
            } else {
                $now = time();

                if ($draftThread !== null && $draftPost !== null) {
                    $draftThread->setSubject($subject);
                    $draftThread->setVisible(1);
                    $draftPost->setSubject($subject);
                    $draftPost->setMessage($message);
                    $draftPost->setVisible(1);
                    $draftPost->setDateline($now);
                    $thread = $draftThread;
                    $post = $draftPost;
                } else {
                    $thread = new Thread();
                    $thread->setFid($fid);
                    $thread->setSubject($subject);
                    $thread->setUid($user->getUid() ?? 0);
                    $thread->setUsername($user->getUsername());
                    $thread->setDateline($now);

                    $em->persist($thread);
                    $em->flush();

                    $post = new Post();
                    $post->setThread($thread);
                    $post->setUid($user->getUid() ?? 0);
                    $post->setUsername($user->getUsername());
                    $post->setSubject($subject);
                    $post->setMessage($message);
                    $post->setDateline($now);

                    $em->persist($post);
                    $em->flush();
                }

                $files = $request->files->get('attachments') ?? [];
                $files = is_array($files) ? $files : ($files ? [$files] : []);
                if (count($files) > 0) {
                    $attachmentService->processAttachments($files, $post, $thread, $user);
                }

                return $this->redirectToRoute('app_thread_show', ['tid' => $thread->getTid()]);
            }
        }

        return $this->render('default/thread/new.html.twig', [
            'forum' => $forum,
            'error' => $error,
            'draft_subject' => $draftPost?->getSubject() ?? '',
            'draft_message' => $draftPost?->getMessage() ?? '',
            'draft_tid' => $draftThread?->getTid(),
        ]);
    }

    #[Route('/newreply.php', name: 'app_thread_reply', priority: 100, methods: ['GET', 'POST'])]
    public function newReply(
        Request $request,
        ThreadRepository $threadRepository,
        EntityManagerInterface $em,
        AttachmentUploadService $attachmentService,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            $this->addFlash('error', 'Yanit yazmak icin giris yapmaniz gerekiyor.');

            return $this->redirectToRoute('app_login');
        }

        $tid = $request->query->getInt('tid');
        $editDraftPid = $request->query->getInt('editdraft');

        if ($tid <= 0) {
            throw $this->createNotFoundException('Thread ID gerekli.');
        }

        $thread = $threadRepository->find($tid);

        if ($thread === null) {
            throw $this->createNotFoundException(
                sprintf('Konu bulunamadi: tid=%d', $tid)
            );
        }

        $draftPost = null;
        if ($editDraftPid > 0) {
            $draftPost = $em->getRepository(Post::class)->find($editDraftPid);
            if ($draftPost === null || $draftPost->getVisible() !== 2 || $draftPost->getThread()?->getTid() !== $tid || $draftPost->getUid() !== $user->getUid()) {
                throw $this->createNotFoundException('Taslak bulunamadi.');
            }
        }

        $quotePost = null;
        $replyTo = $request->query->getInt('replyto');
        if ($replyTo > 0) {
            $quotePost = $em->getRepository(Post::class)->find($replyTo);
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $message = trim($request->request->getString('message'));
            $savedraft = $request->request->has('savedraft');
            $draftPid = $request->request->getInt('draft_pid');

            if ($draftPid > 0) {
                $dp = $em->getRepository(Post::class)->find($draftPid);
                if ($dp !== null && $dp->getVisible() === 2 && $dp->getThread()?->getTid() === $tid && $dp->getUid() === $user->getUid()) {
                    $draftPost = $dp;
                } else {
                    $draftPost = null;
                }
            }

            if ($savedraft) {
                $now = time();
                if ($draftPost !== null) {
                    $draftPost->setMessage($message);
                    $draftPost->setDateline($now);
                    $em->flush();
                } else {
                    $post = new Post();
                    $post->setThread($thread);
                    $post->setUid($user->getUid() ?? 0);
                    $post->setUsername($user->getUsername());
                    $post->setSubject('RE: ' . $thread->getSubject());
                    $post->setMessage($message);
                    $post->setDateline($now);
                    $post->setVisible(2);

                    $em->persist($post);
                    $em->flush();
                }
                $this->addFlash('success', 'Taslak kaydedildi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'drafts']);
            }

            if ($message === '') {
                $error = 'Mesaj alani zorunludur.';
            } else {
                $now = time();

                if ($draftPost !== null) {
                    $draftPost->setMessage($message);
                    $draftPost->setVisible(1);
                    $draftPost->setDateline($now);
                    $thread->incrementReplies();
                    $post = $draftPost;
                } else {
                    $post = new Post();
                    $post->setThread($thread);
                    $post->setUid($user->getUid() ?? 0);
                    $post->setUsername($user->getUsername());
                    $post->setSubject('RE: ' . $thread->getSubject());
                    $post->setMessage($message);
                    $post->setDateline($now);

                    $thread->incrementReplies();

                    $em->persist($post);
                    $em->flush();
                }

                $files = $request->files->get('attachments') ?? [];
                $files = is_array($files) ? $files : ($files ? [$files] : []);
                if (count($files) > 0) {
                    $attachmentService->processAttachments($files, $post, $thread, $user);
                }

                return $this->redirectToRoute('app_thread_show', ['tid' => $thread->getTid()]);
            }
        }

        $initialMessage = '';
        if ($draftPost !== null) {
            $initialMessage = $draftPost->getMessage();
        } elseif ($quotePost !== null) {
            $initialMessage = '> **' . $quotePost->getUsername() . '** yazmis:\n> ' . strip_tags($quotePost->getMessage()) . "\n\n";
        }

        return $this->render('default/thread/reply.html.twig', [
            'thread' => $thread,
            'quote_post' => $quotePost,
            'error' => $error,
            'draft_message' => $initialMessage,
            'draft_pid' => $draftPost?->getPid(),
        ]);
    }

    #[Route('/editpost.php', name: 'app_post_edit', priority: 100, methods: ['GET', 'POST'])]
    public function editPost(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            $this->addFlash('error', 'Mesaj duzenlemek icin giris yapmaniz gerekiyor.');

            return $this->redirectToRoute('app_login');
        }

        $pid = $request->query->getInt('pid');

        if ($pid <= 0) {
            throw $this->createNotFoundException('Post ID gerekli.');
        }

        $post = $em->getRepository(Post::class)->find($pid);

        if ($post === null) {
            throw $this->createNotFoundException(
                sprintf('Mesaj bulunamadi: pid=%d', $pid)
            );
        }

        $thread = $post->getThread();
        $isSoftDeleted = $post->getVisible() === -1;

        // Restore (sadece mod/admin, sadece soft-deleted icin)
        if ($isSoftDeleted && $request->isMethod('POST') && $request->request->getInt('restore') === 1) {
            if (!$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Silinmis mesaji geri alma yetkiniz yok.');
            }
            if (!$this->isCsrfTokenValid('restore_post_' . $pid, $request->request->getString('_token', ''))) {
                throw $this->createAccessDeniedException('Gecersiz CSRF token.');
            }
            $firstPostPid = $em->getRepository(Post::class)->createQueryBuilder('p')
                ->select('MIN(p.pid)')
                ->where('p.thread = :thread')
                ->setParameter('thread', $thread)
                ->getQuery()
                ->getSingleScalarResult();
            $wasFirstPost = $firstPostPid === $post->getPid();
            $post->setVisible(1);
            if ($wasFirstPost && $thread !== null) {
                $thread->setVisible(1);
            } else {
                $thread?->incrementReplies();
            }
            $em->flush();
            $this->addFlash('success', 'Mesaj geri yuklendi.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $thread?->getTid()]);
        }

        // Duzenleme: sadece yazar veya admin, soft-deleted degilse
        if ($isSoftDeleted && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Bu mesaj silinmis.');
        }
        if (!$isSoftDeleted && $user->getUid() !== $post->getUid() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Bu mesaji duzenleme yetkiniz yok.');
        }

        $error = null;

        if (!$isSoftDeleted && $request->isMethod('POST')) {
            $message = trim($request->request->getString('message'));

            if ($message === '') {
                $error = 'Mesaj alani zorunludur.';
            } else {
                $post->setMessage($message);
                $em->flush();

                return $this->redirectToRoute('app_thread_show', ['tid' => $post->getThread()?->getTid()]);
            }
        }

        return $this->render('default/thread/edit.html.twig', [
            'post' => $post,
            'error' => $error,
            'is_soft_deleted' => $isSoftDeleted,
        ]);
    }

    #[Route('/post/{pid}/delete', name: 'app_post_delete', methods: ['POST'])]
    public function deletePost(
        Request $request,
        int $pid,
        EntityManagerInterface $em,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw $this->createAccessDeniedException('Oturum acmaniz gerekiyor.');
        }

        if (!$this->isCsrfTokenValid('delete_post_' . $pid, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Gecersiz CSRF token.');
        }

        $post = $em->getRepository(Post::class)->find($pid);

        if ($post === null) {
            throw $this->createNotFoundException('Mesaj bulunamadi.');
        }

        // Yetkilendirme: Sadece yazar veya yonetici silebilir
        if ($user->getUid() !== $post->getUid() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Bu mesaji silme yetkiniz yok.');
        }

        $thread = $post->getThread();
        if ($thread === null) {
            throw $this->createNotFoundException('Bagli konu bulunamadi.');
        }

        // Soft-delete: visible=-1 (MyBB uyumlu)
        $firstPostPid = $em->getRepository(Post::class)->createQueryBuilder('p')
            ->select('MIN(p.pid)')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getSingleScalarResult();
        $isFirstPost = $firstPostPid === $post->getPid();

        $post->setVisible(-1);
        if ($isFirstPost) {
            $thread->setVisible(-1);
        } else {
            $thread->decrementReplies();
        }
        $em->flush();

        $this->addFlash('success', $isFirstPost ? 'Konu taslak olarak silindi. Moderatorler geri alabilir.' : 'Mesaj taslak olarak silindi. Moderatorler geri alabilir.');
        return $this->redirectToRoute($isFirstPost ? 'app_forum_display' : 'app_thread_show', $isFirstPost ? ['fid' => $thread->getFid()] : ['tid' => $thread->getTid()]);
    }

    #[Route('/thread/{tid}/moderate', name: 'app_thread_moderate', methods: ['POST'])]
    public function moderateThread(int $tid, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $thread = $em->getRepository(Thread::class)->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('moderate_thread_' . $thread->getTid(), $token)) {
            throw $this->createAccessDeniedException('Gecersiz CSRF token.');
        }

        $action = $request->request->getString('action');

        if ($action === 'close') {
            $thread->setClosed('1');
            $this->addFlash('success', 'Konu kilitlendi.');
        } elseif ($action === 'open') {
            $thread->setClosed('');
            $this->addFlash('success', 'Konu kilidi acildi.');
        } elseif ($action === 'stick') {
            $thread->setStickied(1);
            $this->addFlash('success', 'Konu sabitlendi.');
        } elseif ($action === 'unstick') {
            $thread->setStickied(0);
            $this->addFlash('success', 'Konu sabitlemesi kaldirildi.');
        }

        $em->flush();

        return $this->redirectToRoute('app_thread_show', ['tid' => $thread->getTid()]);
    }
}
