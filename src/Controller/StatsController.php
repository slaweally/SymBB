<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    #[Route('/stats.php', name: 'app_stats', priority: 100)]
    public function __invoke(
        UserRepository $userRepository,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
    ): Response {
        return $this->render('default/stats/stats.html.twig', [
            'totalUsers' => $userRepository->countAll(),
            'totalThreads' => $threadRepository->countAll(),
            'totalPosts' => $postRepository->countAll(),
        ]);
    }
}
