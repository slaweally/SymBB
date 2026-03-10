<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ForumRepository;
use App\Repository\ThreadRepository;
use App\Entity\User;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForumController extends AbstractController
{
    #[Route('/forumdisplay.php', name: 'app_forum_display', priority: 100)]
    public function __invoke(
        Request $request,
        ForumRepository $forumRepository,
        ThreadRepository $threadRepository,
        PaginatorInterface $paginator
    ): Response {
        $fid = $request->query->getInt('fid');

        if ($fid <= 0) {
            throw $this->createNotFoundException('Forum ID gerekli.');
        }

        $forum = $forumRepository->find($fid);

        if ($forum === null) {
            throw $this->createNotFoundException(
                sprintf('Forum bulunamadi: fid=%d', $fid)
            );
        }

        $page = $request->query->getInt('page', 1);
        $limit = 20;

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user !== null && $user->getTpp() > 0) {
            $limit = $user->getTpp();
        }

        $queryBuilder = $threadRepository->createQueryBuilder('t')
            ->where('t.fid = :fid')
            ->andWhere('t.visible = 1')
            ->setParameter('fid', $fid)
            ->orderBy('t.dateline', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $page,
            $limit
        );

        return $this->render('default/forum/display.html.twig', [
            'forum' => $forum,
            'pagination' => $pagination,
        ]);
    }
}
