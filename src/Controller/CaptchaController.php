<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CaptchaController
{
    /** 1x1 transparent GIF so forms that reference captcha.php don't break. */
    private const TRANSPARENT_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    #[Route('/captcha.php', name: 'app_captcha', priority: 100)]
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
