<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\ReportedContent;
use App\Entity\User;
use App\Repository\ReportedContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends AbstractController
{
    #[Route('/report.php', name: 'app_report', priority: 100)]
    public function report(
        Request $request,
        EntityManagerInterface $em,
        ReportedContentRepository $reportedContentRepository,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            if ($request->isMethod('GET')) {
                $this->addFlash('error', 'Rapor icin giris yapmaniz gerekiyor.');
                return $this->redirectToRoute('app_login');
            }
            return $this->json(['success' => false, 'error' => 'Oturum açmanız gerekiyor.'], 403);
        }

        $pid = $request->query->getInt('pid') ?: $request->request->getInt('pid');
        if ($pid <= 0) {
            if ($request->isMethod('GET')) {
                throw $this->createNotFoundException('Mesaj ID gerekli.');
            }
            return $this->json(['success' => false, 'error' => 'Geçersiz mesaj ID.'], 400);
        }

        $post = $em->getRepository(Post::class)->find($pid);
        if ($post === null) {
            if ($request->isMethod('GET')) {
                throw $this->createNotFoundException('Mesaj bulunamadi.');
            }
            return $this->json(['success' => false, 'error' => 'Mesaj bulunamadı.'], 404);
        }

        if ($request->isMethod('GET')) {
            $uid = $user->getUid() ?? 0;
            $alreadyReported = $reportedContentRepository->hasUserReportedPost($uid, $pid);
            return $this->render('default/report/form.html.twig', [
                'post' => $post,
                'already_reported' => $alreadyReported,
            ]);
        }

        if (!$this->isCsrfTokenValid('report_content', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'error' => 'Geçersiz istek.'], 403);
        }

        if ($request->request->getString('action') !== 'report') {
            return $this->json(['success' => false, 'error' => 'Geçersiz işlem.'], 400);
        }
        $reason = trim($request->request->getString('reason') ?? '');

        $uid = $user->getUid() ?? 0;
        if ($reportedContentRepository->hasUserReportedPost($uid, $pid)) {
            return $this->json(['success' => false, 'error' => 'Bu mesajı zaten raporladınız.'], 400);
        }

        $report = new ReportedContent();
        $report->setCid($pid);
        $report->setType('post');
        $report->setUid($uid);
        $report->setReason($reason);
        $report->setDateline(time());

        $em->persist($report);
        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return $this->json(['success' => true, 'message' => 'Rapor modlara iletildi.']);
        }
        $this->addFlash('success', 'Rapor modlara iletildi.');
        return $this->redirectToRoute('app_thread_show', ['tid' => $post->getThread()?->getTid()]);
    }
}
