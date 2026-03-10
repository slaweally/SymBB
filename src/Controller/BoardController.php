<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\ReportedContentRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoardController extends AbstractController
{
    #[Route('/', name: 'app_board_index', priority: 100)]
    #[Route('/index.php', name: 'app_board_index_legacy', priority: 100)]
    public function __invoke(
        ForumRepository $forumRepository,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
        UserRepository $userRepository,
        ReportedContentRepository $reportedContentRepository,
    ): Response {
        $totalThreads = $threadRepository->countAll();
        $totalPosts = $postRepository->countAll();
        $totalMembers = $userRepository->countAll();
        $lastMember = $userRepository->findLastRegistered();
        $activeUsers = $userRepository->findActiveUsers(time() - 900);
        $pendingReportCount = 0;
        if ($this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN')) {
            $pendingReportCount = $reportedContentRepository->countPendingPostReports();
        }

        $forumData = $forumRepository->findCategoriesWithForums();

        return $this->render('default/board/index.html.twig', [
            'forum_data' => $forumData,
            'total_threads' => $totalThreads,
            'total_posts' => $totalPosts,
            'total_members' => $totalMembers,
            'last_member' => $lastMember,
            'active_users' => $activeUsers,
            'pending_report_count' => $pendingReportCount,
        ]);
    }
}
