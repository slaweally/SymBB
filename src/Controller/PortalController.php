<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\ReportedContentRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortalController extends AbstractController
{
    private const ANNOUNCEMENT_FORUM_ID = 2;

    #[Route('/portal.php', name: 'app_portal', priority: 100)]
    public function __invoke(
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
        UserRepository $userRepository,
        ReportedContentRepository $reportedContentRepository,
    ): Response {
        $announcements = $threadRepository->findAnnouncements(self::ANNOUNCEMENT_FORUM_ID, 5);
        $latestPosts = $postRepository->findLatest(10);

        $totalThreads = $threadRepository->countAll();
        $totalPosts = $postRepository->countAll();
        $totalMembers = $userRepository->countAll();
        $lastMember = $userRepository->findLastRegistered();
        $activeUsers = $userRepository->findActiveUsers(time() - 900);
        $pendingReportCount = 0;
        if ($this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN')) {
            $pendingReportCount = $reportedContentRepository->countPendingPostReports();
        }

        return $this->render('default/portal/index.html.twig', [
            'announcements' => $announcements,
            'latest_posts' => $latestPosts,
            'total_threads' => $totalThreads,
            'total_posts' => $totalPosts,
            'total_members' => $totalMembers,
            'last_member' => $lastMember,
            'active_users' => $activeUsers,
            'pending_report_count' => $pendingReportCount,
        ]);
    }
}
