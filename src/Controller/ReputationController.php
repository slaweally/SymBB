<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Reputation;
use App\Entity\User;
use App\Repository\ReputationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReputationController extends AbstractController
{
    #[Route('/reputation.php', name: 'app_reputation', priority: 100)]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        ReputationRepository $reputationRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->add($request, $em, $reputationRepository);
        }
        $uid = $request->query->getInt('uid');
        if ($uid > 0) {
            return $this->view($request, $em, $reputationRepository, $uid);
        }
        return $this->redirectToRoute('app_board_index');
    }

    private function view(
        Request $request,
        EntityManagerInterface $em,
        ReputationRepository $reputationRepository,
        int $uid,
    ): Response {
        $user = $em->getRepository(User::class)->find($uid);
        if ($user === null) {
            throw $this->createNotFoundException('Kullanici bulunamadi.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $reputations = $reputationRepository->findByUser($uid, $perPage, $offset);
        $total = $reputationRepository->countByUser($uid);

        $adduids = array_unique(array_map(fn ($r) => $r->getAdduid(), $reputations));
        $addUsers = [];
        if (count($adduids) > 0) {
            $users = $em->getRepository(User::class)->findBy(['uid' => $adduids]);
            foreach ($users as $u) {
                $addUsers[$u->getUid()] = $u;
            }
        }

        $pids = array_filter(array_map(fn ($r) => $r->getPid(), $reputations));
        $posts = [];
        if (count($pids) > 0) {
            $postList = $em->getRepository(Post::class)->findBy(['pid' => $pids]);
            foreach ($postList as $p) {
                $posts[$p->getPid()] = $p;
            }
        }

        return $this->render('default/reputation/view.html.twig', [
            'target_user' => $user,
            'reputations' => $reputations,
            'add_users' => $addUsers,
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function add(
        Request $request,
        EntityManagerInterface $em,
        ReputationRepository $reputationRepository,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(['success' => false, 'error' => 'Oturum acmaniz gerekiyor.'], 403);
        }

        if (!$this->isCsrfTokenValid('reputation_add', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'error' => 'Gecersiz istek.'], 403);
        }

        $targetUid = $request->request->getInt('target_uid');
        $pid = $request->request->getInt('pid');
        $repValue = $request->request->getInt('rep_value');
        $comment = trim($request->request->getString('comment') ?? '');

        if ($targetUid <= 0) {
            return $this->json(['success' => false, 'error' => 'Gecersiz hedef kullanici.'], 400);
        }

        if ($repValue !== 1 && $repValue !== -1) {
            return $this->json(['success' => false, 'error' => 'Puan +1 veya -1 olmali.'], 400);
        }

        if ($user->getUid() === $targetUid) {
            return $this->json(['success' => false, 'error' => 'Kendinize rep veremezsiniz.'], 400);
        }

        if ($pid > 0 && $reputationRepository->hasUserGivenRepToPost($user->getUid() ?? 0, $pid)) {
            return $this->json(['success' => false, 'error' => 'Bu mesaja zaten rep verdiniz.'], 400);
        }

        $targetUser = $em->getRepository(User::class)->find($targetUid);
        if ($targetUser === null) {
            return $this->json(['success' => false, 'error' => 'Hedef kullanici bulunamadi.'], 404);
        }

        if ($pid > 0) {
            $post = $em->getRepository(Post::class)->find($pid);
            if ($post === null || $post->getUid() !== $targetUid) {
                return $this->json(['success' => false, 'error' => 'Mesaj bulunamadi veya kullaniciya ait degil.'], 400);
            }
        }

        $reputation = new Reputation();
        $reputation->setUid($targetUid);
        $reputation->setAdduid($user->getUid() ?? 0);
        $reputation->setPid($pid);
        $reputation->setReputation($repValue);
        $reputation->setComments($comment);
        $reputation->setDateline(time());

        $em->persist($reputation);

        $targetUser->setReputation($targetUser->getReputation() + $repValue);
        $em->flush();

        $redirect = $request->request->getString('redirect') ?: $request->headers->get('Referer', '');

        return $this->json([
            'success' => true,
            'message' => 'Rep puaniniz eklendi.',
            'redirect' => $redirect,
        ]);
    }
}
