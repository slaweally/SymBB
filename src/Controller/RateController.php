<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Thread;
use App\Entity\ThreadRating;
use App\Entity\User;
use App\Repository\ThreadRatingRepository;
use App\Repository\ThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RateController extends AbstractController
{
    #[Route('/ratethread.php', name: 'app_rate_thread', priority: 100, methods: ['POST'])]
    public function rate(
        Request $request,
        ThreadRepository $threadRepository,
        ThreadRatingRepository $ratingRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('rate_thread', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Geçersiz istek.');

            return $this->redirectToRoute('app_board_index');
        }

        $tid = $request->request->getInt('tid');
        $rating = $request->request->getInt('rating');

        if ($tid <= 0) {
            throw $this->createNotFoundException('Geçersiz konu ID.');
        }

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Puan 1 ile 5 arasında olmalıdır.');

            return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
        }

        $thread = $threadRepository->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadı.');
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($thread->getUid() === $user->getUid()) {
            $this->addFlash('error', 'Kendi konunuza puan veremezsiniz.');

            return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
        }

        if ($ratingRepository->hasUserRated($tid, $user->getUid())) {
            return new Response('Bu konuya zaten puan verdiniz.', Response::HTTP_FORBIDDEN);
        }

        $threadRating = new ThreadRating();
        $threadRating->setTid($tid);
        $threadRating->setUid($user->getUid());
        $threadRating->setRating($rating);
        $threadRating->setDateline(time());

        $thread->incrementNumratings();
        $thread->addToTotalratings($rating);

        $em->persist($threadRating);
        $em->flush();

        $this->addFlash('success', 'Puanınız kaydedildi.');

        return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
    }
}
