<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Thread;
use App\Repository\ForumRepository;
use App\Repository\ThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModerationController extends AbstractController
{
    #[Route('/moderation.php', name: 'app_moderation', priority: 100)]
    public function __invoke(
        Request $request,
        ForumRepository $forumRepository,
        ThreadRepository $threadRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $action = $request->query->getString('action');
        $tid = $request->query->getInt('tid');

        if ($action === 'movethread' && $tid > 0) {
            return $this->handleMoveThread($request, $tid, $forumRepository, $threadRepository, $em);
        }

        return $this->redirectToRoute('app_board_index');
    }

    private function handleMoveThread(
        Request $request,
        int $tid,
        ForumRepository $forumRepository,
        ThreadRepository $threadRepository,
        EntityManagerInterface $em
    ): Response {
        $thread = $threadRepository->find($tid);
        if ($thread === null) {
            $this->addFlash('error', 'Konu bulunamadı.');
            return $this->redirectToRoute('app_board_index');
        }

        if ($request->isMethod('POST')) {
            $targetFid = $request->request->getInt('fid');
            if ($targetFid <= 0) {
                $this->addFlash('error', 'Geçerli bir hedef forum seçin.');
                return $this->redirectToRoute('app_moderation', ['action' => 'movethread', 'tid' => $tid]);
            }

            if ($targetFid === $thread->getFid()) {
                $this->addFlash('error', 'Konu zaten bu forumda.');
                return $this->redirectToRoute('app_moderation', ['action' => 'movethread', 'tid' => $tid]);
            }

            $targetForum = $em->getRepository(\App\Entity\Forum::class)->find($targetFid);
            if ($targetForum === null || $targetForum->getType() !== 'f') {
                $this->addFlash('error', 'Geçersiz hedef forum.');
                return $this->redirectToRoute('app_moderation', ['action' => 'movethread', 'tid' => $tid]);
            }

            $thread->setFid($targetFid);
            $em->flush();

            // Update posts.fid if column exists (MyBB compatibility)
            $conn = $em->getConnection();
            $columns = $conn->createSchemaManager()->listTableColumns('mybb_posts');
            if (isset($columns['fid'])) {
                $conn->executeStatement(
                    'UPDATE mybb_posts SET fid = :fid WHERE tid = :tid',
                    ['fid' => $targetFid, 'tid' => $tid]
                );
            }

            $this->addFlash('success', 'Konu başarıyla taşındı.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
        }

        $forumData = $forumRepository->findCategoriesWithForums();
        $currentFid = $thread->getFid();

        return $this->render('default/moderation/move.html.twig', [
            'thread' => $thread,
            'forum_data' => $forumData,
            'current_fid' => $currentFid,
        ]);
    }
}
