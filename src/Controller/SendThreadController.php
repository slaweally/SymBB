<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SendThreadController extends AbstractController
{
    #[Route('/sendthread.php', name: 'app_sendthread', priority: 100)]
    public function __invoke(Request $request, ThreadRepository $threadRepository): Response
    {
        $tid = $request->query->getInt('tid', 0);
        if ($tid <= 0) {
            throw $this->createNotFoundException('Konu ID gerekli.');
        }
        $thread = $threadRepository->find($tid);
        if ($thread === null || $thread->getVisible() !== 1) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }
        $error = null;
        $success = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sendthread', $request->request->getString('_token', ''))) {
                $error = 'Gecersiz istek.';
            } else {
                $toEmail = trim($request->request->getString('email', ''));
                $fromName = trim($request->request->getString('fromname', ''));
                $fromEmail = trim($request->request->getString('fromemail', ''));
                $subject = trim($request->request->getString('subject', ''));
                $message = trim($request->request->getString('message', ''));
                if (!filter_var($toEmail, \FILTER_VALIDATE_EMAIL)) {
                    $error = 'Gecersiz e-posta adresi.';
                } elseif ($fromName === '' || $subject === '' || $message === '') {
                    $error = 'Tum alanlari doldurun.';
                } elseif (!filter_var($fromEmail, \FILTER_VALIDATE_EMAIL)) {
                    $error = 'Gecersiz gonderici e-posta.';
                } else {
                    $threadUrl = $this->generateUrl('app_thread_show', ['tid' => $tid], true);
                    $body = "Merhaba,\n\n{$fromName} size su konuyu gonderdi:\n\nKonu: {$thread->getSubject()}\nLink: {$threadUrl}\n\nEk mesaj:\n{$message}";
                    $sent = @mail($toEmail, $subject, $body, 'From: ' . $fromName . ' <' . $fromEmail . '>', '-f' . $fromEmail);
                    if ($sent) {
                        $success = true;
                        $this->addFlash('success', 'Konu basariyla gonderildi.');
                        return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
                    }
                    $error = 'E-posta gonderilemedi.';
                }
            }
        }
        return $this->render('default/sendthread/index.html.twig', [
            'thread' => $thread,
            'error' => $error,
            'success' => $success,
        ]);
    }
}
