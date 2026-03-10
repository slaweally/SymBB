<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController
{
    /** 1x1 transparent GIF — MyBB task.php returns this while running scheduled tasks. */
    private const TRANSPARENT_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    #[Route('/task.php', name: 'app_task', priority: 100)]
    public function __invoke(): Response
    {
        $response = new Response(self::TRANSPARENT_GIF, Response::HTTP_OK, [
            'Content-Type' => 'image/gif',
            'Expires' => 'Sat, 01 Jan 2000 01:00:00 GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
        $response->setPrivate();
        return $response;
    }
}
