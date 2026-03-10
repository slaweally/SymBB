<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\Post;
use App\Entity\Thread;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentUploadService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'rar'];
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param UploadedFile[] $files
     */
    public function processAttachments(array $files, Post $post, Thread $thread, User $user): void
    {
        $uploadPath = $this->getUploadPath();

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            if ($file->getSize() > self::MAX_SIZE) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $storedName = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;

            $file->move($uploadPath, $storedName);

            $attachment = new Attachment();
            $attachment->setPost($post);
            $attachment->setTid($thread->getTid() ?? 0);
            $attachment->setUid($user->getUid() ?? 0);
            $attachment->setFilename($storedName);
            $attachment->setFiletype($file->getMimeType());
            $attachment->setFilesize((int) $file->getSize());
            $attachment->setAttachname($originalName);
            $attachment->setDateline(time());

            $this->em->persist($attachment);
        }

        $this->em->flush();
    }

    private function getUploadPath(): string
    {
        $fullPath = $this->projectDir . '/public/uploads/attachments';

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        return $fullPath;
    }
}
