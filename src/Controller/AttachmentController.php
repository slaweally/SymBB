<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class AttachmentController extends AbstractController
{
    /**
     * Eski MyBB URL uyumluluğu: attachment.php?aid=X → /attachment/{aid}/view
     */
    #[Route('/attachment.php', name: 'app_attachment_legacy', priority: 200)]
    public function legacy(Request $request, EntityManagerInterface $em): Response
    {
        $aid = $request->query->getInt('aid');
        $thumbnail = $request->query->getInt('thumbnail');

        if ($aid > 0) {
            $attachment = $em->getRepository(Attachment::class)->find($aid);
            if ($attachment !== null) {
                return $this->redirectToRoute(
                    $attachment->isImage() ? 'app_attachment_view' : 'app_attachment_download',
                    ['aid' => $aid],
                    Response::HTTP_MOVED_PERMANENTLY
                );
            }
        }

        if ($thumbnail > 0) {
            $attachment = $em->getRepository(Attachment::class)->find($thumbnail);
            if ($attachment !== null) {
                return $this->redirectToRoute('app_attachment_view', ['aid' => $thumbnail], Response::HTTP_MOVED_PERMANENTLY);
            }
        }

        throw $this->createNotFoundException('Eklenti bulunamadi.');
    }

    #[Route('/attachment/{aid}/download', name: 'app_attachment_download', requirements: ['aid' => '\d+'])]
    public function download(int $aid, EntityManagerInterface $em): Response
    {
        $attachment = $em->getRepository(Attachment::class)->find($aid);

        if ($attachment === null) {
            throw $this->createNotFoundException('Eklenti bulunamadi.');
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/attachments/' . $attachment->getFilename();

        if (!is_file($path)) {
            throw $this->createNotFoundException('Dosya bulunamadi.');
        }

        $attachment->setDownloads($attachment->getDownloads() + 1);
        $em->flush();

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getAttachname()
        );

        return $response;
    }

    #[Route('/attachment/{aid}/view', name: 'app_attachment_view', requirements: ['aid' => '\d+'])]
    public function view(int $aid, EntityManagerInterface $em): Response
    {
        $attachment = $em->getRepository(Attachment::class)->find($aid);

        if ($attachment === null) {
            throw $this->createNotFoundException('Eklenti bulunamadi.');
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/attachments/' . $attachment->getFilename();

        if (!is_file($path)) {
            throw $this->createNotFoundException('Dosya bulunamadi.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment->getFiletype() ?: 'application/octet-stream');

        return $response;
    }
}