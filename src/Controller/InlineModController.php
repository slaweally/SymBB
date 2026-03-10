<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Thread;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InlineModController extends AbstractController
{
    #[Route('/inlinemod.php', name: 'app_inlinemod', priority: 100, methods: ['POST'])]
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $action = $request->request->getString('action');
        $type = $request->request->getString('type');
        $items = $request->request->all('items');

        if (!in_array($action, ['delete', 'lock', 'unlock'], true) || !in_array($type, ['threads', 'posts'], true)) {
            $this->addFlash('error', 'Geçersiz işlem.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        $ids = array_filter(array_map('intval', $items));
        if (empty($ids)) {
            $this->addFlash('error', 'Hiçbir öğe seçilmedi.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        if ($type === 'posts' && !in_array($action, ['delete'], true)) {
            $action = 'delete';
        }

        if (!$this->isCsrfTokenValid('inlinemod', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        if ($type === 'threads') {
            $this->processThreads($em, $action, $ids);
        } else {
            $this->processPosts($em, $action, $ids);
        }

        $this->addFlash('success', 'İşlem tamamlandı.');
        return $this->redirect($request->headers->get('Referer', '/'));
    }

    /** @param int[] $ids */
    private function processThreads(EntityManagerInterface $em, string $action, array $ids): void
    {
        $repo = $em->getRepository(Thread::class);

        if ($action === 'delete') {
            $threads = $repo->findBy(['tid' => $ids]);
            foreach ($threads as $thread) {
                foreach ($thread->getPosts() as $post) {
                    $em->remove($post);
                }
                $em->remove($thread);
            }
            $em->flush();
            return;
        }

        $value = $action === 'lock' ? '1' : '0';
        $em->createQueryBuilder()
            ->update(Thread::class, 't')
            ->set('t.closed', ':closed')
            ->where('t.tid IN (:ids)')
            ->setParameter('closed', $value)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /** @param int[] $ids */
    private function processPosts(EntityManagerInterface $em, string $action, array $ids): void
    {
        $repo = $em->getRepository(Post::class);
        $posts = $repo->findBy(['pid' => $ids]);
        foreach ($posts as $post) {
            $thread = $post->getThread();
            if ($thread !== null) {
                $thread->decrementReplies();
            }
            $em->remove($post);
        }
        $em->flush();
    }
}
